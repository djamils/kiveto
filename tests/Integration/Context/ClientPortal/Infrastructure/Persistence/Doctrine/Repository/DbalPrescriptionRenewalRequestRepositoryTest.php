<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Domain\Request\ClosureReason;
use App\Context\ClientPortal\Domain\Request\PetCondition;
use App\Context\ClientPortal\Domain\Request\PortalRequestId;
use App\Context\ClientPortal\Domain\Request\PrescriptionRenewalRequest;
use App\Context\ClientPortal\Domain\Request\RequestResolution;
use App\Context\ClientPortal\Domain\Request\RequestStatus;
use App\Context\ClientPortal\Domain\Request\RequestSubject;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\DbalPrescriptionRenewalRequestRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** The unique open key under concurrency is covered by Concurrency\RenewalRequestRaceTest. */
final class DbalPrescriptionRenewalRequestRepositoryTest extends KernelTestCase
{
    private const string R1        = '01960000-0000-7000-8000-0000000a8001';
    private const string R2        = '01960000-0000-7000-8000-0000000a8002';
    private const string R3        = '01960000-0000-7000-8000-0000000a8003';
    private const string R4        = '01960000-0000-7000-8000-0000000a8004';
    private const string CLINIC    = '01960000-0000-7000-8000-0000000a8101';
    private const string CLINIC2   = '01960000-0000-7000-8000-0000000a8102';
    private const string CLIENT    = '01960000-0000-7000-8000-0000000a8201';
    private const string USER      = '01960000-0000-7000-8000-0000000a8301';
    private const string PET       = '01960000-0000-7000-8000-0000000a8401';
    private const string RX        = '01960000-0000-7000-8000-0000000a8501';
    private const string RX2       = '01960000-0000-7000-8000-0000000a8502';
    private const string RESULTING = '01960000-0000-7000-8000-0000000a8503';
    private const string VET       = '01960000-0000-7000-8000-0000000a8601';
    private const string MESSAGE   = '01960000-0000-7000-8000-0000000a8701';

    public function testARequestIsReadBackWithEveryFieldAndItsAnswer(): void
    {
        $repository = $this->repository();
        self::assertTrue($repository->add(self::request(self::R1)));

        $found = $repository->findById(self::CLINIC, self::R1);
        self::assertNotNull($found);
        self::assertSame(
            [self::R1, self::CLINIC, self::CLIENT, self::USER, self::RX, self::PET, PetCondition::ADVERSE_EFFECTS, 'Vomit', null, RequestStatus::SUBMITTED, '2026-10-28 08:00:00', '2026-10-28 08:00:00', null, null, null, null, false],
            [
                $found->id()->toString(), $found->clinicId(), $found->clientId(), $found->userId(), $found->prescriptionId(), $found->petId(), $found->petCondition(), $found->note(),
                $found->resultingPrescriptionId(), $found->status(), $found->submittedAt()->format('Y-m-d H:i:s'), $found->updatedAt()->format('Y-m-d H:i:s'),
                $found->resolution(), $found->closedAt(), $found->closedReason(), $found->notificationMessageId(), $found->isClientUnreachable(),
            ],
        );

        $found->resolve(RequestResolution::of(RequestStatus::DONE, self::VET, 'Dr Claire Lambert', null, self::utc('2026-10-29 08:00:00')), self::RESULTING);
        $found->attachNotification(self::MESSAGE);
        $found->eraseFreeText();
        $repository->save($found);

        $answered = $repository->findByIdForUpdate(self::CLINIC, self::R1);
        self::assertSame([RequestStatus::DONE, self::RESULTING, 'Dr Claire Lambert', null, self::MESSAGE, null], [
            $answered?->status(), $answered?->resultingPrescriptionId(), $answered?->resolution()?->resolvedByName(), $answered?->resolution()?->message(), $answered?->notificationMessageId(), $answered?->note(),
        ]);
        self::assertNull($repository->findById(self::CLINIC2, self::R1), 'another clinic');
        self::assertNull($repository->findById(self::CLINIC, 'not-a-uuid'));
    }

    public function testOneOpenRequestPerPrescriptionAndANewOneOnceItIsAnswered(): void
    {
        $repository = $this->repository();
        self::assertTrue($repository->add(self::request(self::R1)));
        self::assertFalse($repository->add(self::request(self::R2)), 'the open key refuses a second submitted request');
        self::assertNull($repository->findById(self::CLINIC, self::R2));
        self::assertTrue($repository->add(self::request(self::R3, clinic: self::CLINIC2)), 'another clinic');

        self::assertSame(self::R1, $repository->findSubmittedForPrescription(self::CLINIC, self::RX)?->id()->toString());
        self::assertSame(self::R1, $repository->findSubmittedForPrescriptionForUpdate(self::CLINIC, self::RX)?->id()->toString());

        $rejected = $repository->findById(self::CLINIC, self::R1);
        \assert($rejected instanceof PrescriptionRenewalRequest);
        $rejected->resolve(RequestResolution::of(RequestStatus::REJECTED, self::VET, 'Dr Claire Lambert', 'Non', self::utc('2026-10-29 08:00:00')));
        $repository->save($rejected);

        self::assertNull($repository->findSubmittedForPrescription(self::CLINIC, self::RX));
        self::assertSame(self::R1, $repository->findLatestRejected(self::CLINIC, self::RX)?->id()->toString());
        self::assertNull($repository->findLatestRejected(self::CLINIC, self::RX2));
        self::assertTrue($repository->add(self::request(self::R2, at: '2026-10-30 08:00:00')), 'the refused request no longer holds the key');
        self::assertSame(self::R2, $repository->findSubmittedForPrescription(self::CLINIC, self::RX)?->id()->toString());
    }

    public function testTheLatestRequestOfEachPrescriptionAndTheOpenOnesOfASubject(): void
    {
        $repository = $this->repository();
        $closed     = self::request(self::R1, at: '2026-10-20 08:00:00');
        $closed->close(ClosureReason::PRESCRIPTION_CANCELLED, self::utc('2026-10-21 08:00:00'));
        $repository->add($closed);
        $repository->add(self::request(self::R2, at: '2026-10-25 08:00:00'));
        $repository->add(self::request(self::R3, rx: self::RX2, at: '2026-10-26 08:00:00'));
        $repository->add(self::request(self::R4, rx: self::RX2, clinic: self::CLINIC2));

        $latest = $repository->latestByPrescriptions(self::CLINIC, [self::RX, self::RX2, self::RESULTING, 'not-a-uuid']);
        self::assertSame([self::RX => self::R2, self::RX2 => self::R3], array_map(static fn (PrescriptionRenewalRequest $r): string => $r->id()->toString(), $latest));
        self::assertSame([], $repository->latestByPrescriptions(self::CLINIC, ['not-a-uuid']));

        $ids = static fn (array $requests): array => array_map(static fn (mixed $r): string => $r instanceof PrescriptionRenewalRequest ? $r->id()->toString() : '', $requests);
        self::assertSame([self::R2, self::R3], $ids($repository->findSubmittedForSubjectForUpdate(RequestSubject::CLIENT, self::CLIENT, self::CLINIC)));
        self::assertSame([self::R2], $ids($repository->findSubmittedForSubjectForUpdate(RequestSubject::PRESCRIPTION, self::RX, self::CLINIC)));
        self::assertSame([self::R2, self::R3], $ids($repository->findSubmittedForSubjectForUpdate(RequestSubject::PET, self::PET, self::CLINIC)));
        self::assertSame([self::R2, self::R3, self::R4], $ids($repository->findSubmittedForSubjectForUpdate(RequestSubject::USER, self::USER, null)));
        self::assertSame([], $repository->findSubmittedForSubjectForUpdate(RequestSubject::APPOINTMENT, self::RX, self::CLINIC));
        self::assertSame([self::R1, self::R2, self::R3, self::R4], $ids($repository->findByUser(self::USER)));
    }

    private function repository(): DbalPrescriptionRenewalRequestRepository
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return new DbalPrescriptionRenewalRequestRepository($connection);
    }

    private static function request(string $id, string $rx = self::RX, string $clinic = self::CLINIC, string $at = '2026-10-28 08:00:00'): PrescriptionRenewalRequest
    {
        return PrescriptionRenewalRequest::submit(PortalRequestId::fromString($id), $clinic, self::CLIENT, self::USER, $rx, self::PET, PetCondition::ADVERSE_EFFECTS, 'Vomit', self::utc($at));
    }

    private static function utc(string $at): \DateTimeImmutable
    {
        return new \DateTimeImmutable($at, new \DateTimeZone('UTC'));
    }
}
