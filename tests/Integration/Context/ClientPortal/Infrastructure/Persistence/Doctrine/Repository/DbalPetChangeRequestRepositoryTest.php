<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Domain\Request\ChangeField;
use App\Context\ClientPortal\Domain\Request\ClosureReason;
use App\Context\ClientPortal\Domain\Request\PetChangeRequest;
use App\Context\ClientPortal\Domain\Request\PortalRequestId;
use App\Context\ClientPortal\Domain\Request\ProposedChange;
use App\Context\ClientPortal\Domain\Request\RequestResolution;
use App\Context\ClientPortal\Domain\Request\RequestStatus;
use App\Context\ClientPortal\Domain\Request\RequestSubject;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\DbalPetChangeRequestRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DbalPetChangeRequestRepositoryTest extends KernelTestCase
{
    private const string R1      = '01960000-0000-7000-8000-0000000a7001';
    private const string R2      = '01960000-0000-7000-8000-0000000a7002';
    private const string R3      = '01960000-0000-7000-8000-0000000a7003';
    private const string CLINIC  = '01960000-0000-7000-8000-0000000a7101';
    private const string CLINIC2 = '01960000-0000-7000-8000-0000000a7102';
    private const string CLIENT  = '01960000-0000-7000-8000-0000000a7201';
    private const string USER    = '01960000-0000-7000-8000-0000000a7301';
    private const string USER2   = '01960000-0000-7000-8000-0000000a7302';
    private const string PET     = '01960000-0000-7000-8000-0000000a7401';
    private const string PET2    = '01960000-0000-7000-8000-0000000a7402';
    private const string VET     = '01960000-0000-7000-8000-0000000a7501';
    private const string MESSAGE = '01960000-0000-7000-8000-0000000a7601';

    public function testARequestIsReadBackWithEveryFieldAndItsAnswer(): void
    {
        $repository = $this->repository();
        $repository->save(self::request(self::R1));

        $found = $repository->findById(self::CLINIC, self::R1);
        self::assertNotNull($found);
        // MySQL stores a JSON object with its keys sorted
        self::assertSame(
            [self::R1, self::CLINIC, self::CLIENT, self::USER, self::PET, ChangeField::WEIGHT, '12,4', ['measuredOn' => '2026-10-27', 'weightGrams' => 12400], 'Pesé à la maison', '11800', RequestStatus::SUBMITTED, '2026-10-28 08:00:00', '2026-10-28 08:00:00', null, null, null, null, false],
            [
                $found->id()->toString(), $found->clinicId(), $found->clientId(), $found->userId(), $found->petId(), $found->change()->field(), $found->change()->proposedValue(),
                $found->change()->normalised(), $found->change()->note(), $found->currentValueAtSubmit(), $found->status(), $found->submittedAt()->format('Y-m-d H:i:s'),
                $found->updatedAt()->format('Y-m-d H:i:s'), $found->resolution(), $found->closedAt(), $found->closedReason(), $found->notificationMessageId(), $found->isClientUnreachable(),
            ],
        );

        $found->resolve(RequestResolution::of(RequestStatus::REJECTED, self::VET, 'Dr Claire Lambert', 'Pesez-le à la clinique.', self::utc('2026-10-29 08:00:00')));
        $found->attachNotification(self::MESSAGE);
        $found->eraseFreeText();
        $repository->save($found);

        $answered = $repository->findByIdForUpdate(self::CLINIC, self::R1);
        self::assertSame([RequestStatus::REJECTED, '2026-10-29 08:00:00', self::VET, 'Dr Claire Lambert', 'Pesez-le à la clinique.', self::MESSAGE, null, '2026-10-29 08:00:00'], [
            $answered?->status(), $answered?->resolution()?->resolvedAt()->format('Y-m-d H:i:s'), $answered?->resolution()?->resolvedByUserId(), $answered?->resolution()?->resolvedByName(),
            $answered?->resolution()?->message(), $answered?->notificationMessageId(), $answered?->change()->note(), $answered?->updatedAt()->format('Y-m-d H:i:s'),
        ]);

        $closed = self::request(self::R2, field: ChangeField::OTHER);
        $closed->close(ClosureReason::PET_ARCHIVED, self::utc('2026-10-29 08:00:00'));
        $closed->markClientUnreachable();
        $repository->save($closed);
        $found = $repository->findById(self::CLINIC, self::R2);
        self::assertSame([RequestStatus::CLOSED, ClosureReason::PET_ARCHIVED, '2026-10-29 08:00:00', null, true, []], [
            $found?->status(), $found?->closedReason(), $found?->closedAt()?->format('Y-m-d H:i:s'), $found?->resolution(), $found?->isClientUnreachable(), $found?->change()->normalised(),
        ]);

        self::assertNull($repository->findById(self::CLINIC2, self::R1), 'another clinic');
        self::assertNull($repository->findById(self::CLINIC, 'not-a-uuid'));
    }

    public function testOpenRequestsAreCountedAndFoundBySubject(): void
    {
        $repository = $this->repository();
        $repository->save(self::request(self::R1));
        $repository->save(self::request(self::R2, at: '2026-10-27 08:00:00', user: self::USER2));
        $done = self::request(self::R3, pet: self::PET2, clinic: self::CLINIC2);
        $repository->save($done);
        $answered = self::request('01960000-0000-7000-8000-0000000a7004');
        $answered->resolve(RequestResolution::of(RequestStatus::DONE, self::VET, 'Dr Claire Lambert', null, self::utc('2026-10-29 08:00:00')));
        $repository->save($answered);

        self::assertSame(2, $repository->countSubmitted(self::CLINIC, self::PET, ChangeField::WEIGHT));
        self::assertSame(0, $repository->countSubmitted(self::CLINIC, self::PET, ChangeField::NAME));

        $ids = static fn (array $requests): array => array_map(static fn (mixed $r): string => $r instanceof PetChangeRequest ? $r->id()->toString() : '', $requests);
        self::assertSame([self::R2, self::R1], $ids($repository->findSubmittedForSubjectForUpdate(RequestSubject::CLIENT, self::CLIENT, self::CLINIC)), 'oldest first');
        self::assertSame([self::R3], $ids($repository->findSubmittedForSubjectForUpdate(RequestSubject::PET, self::PET2, self::CLINIC2)));
        self::assertSame([self::R1, self::R3], $ids($repository->findSubmittedForSubjectForUpdate(RequestSubject::USER, self::USER, null)));
        self::assertSame([], $repository->findSubmittedForSubjectForUpdate(RequestSubject::PRESCRIPTION, self::PET, self::CLINIC));
        self::assertSame([self::R1, self::R3, '01960000-0000-7000-8000-0000000a7004'], $ids($repository->findByUser(self::USER)));
    }

    private function repository(): DbalPetChangeRequestRepository
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return new DbalPetChangeRequestRepository($connection);
    }

    private static function request(string $id, string $pet = self::PET, string $clinic = self::CLINIC, string $user = self::USER, string $at = '2026-10-28 08:00:00', ChangeField $field = ChangeField::WEIGHT): PetChangeRequest
    {
        $now = self::utc($at);

        return PetChangeRequest::submit(
            PortalRequestId::fromString($id),
            $clinic,
            self::CLIENT,
            $user,
            $pet,
            ChangeField::OTHER === $field
                ? ProposedChange::of($field, '', null, 'Nouvelle adresse', $now)
                : ProposedChange::of($field, '12,4', new \DateTimeImmutable('2026-10-27'), 'Pesé à la maison', $now),
            '11800',
            $now,
        );
    }

    private static function utc(string $at): \DateTimeImmutable
    {
        return new \DateTimeImmutable($at, new \DateTimeZone('UTC'));
    }
}
