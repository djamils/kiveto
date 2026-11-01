<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Application\Port\PortalRequestRecord;
use App\Context\ClientPortal\Application\Port\RequestPosition;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Context\ClientPortal\Domain\Request\ChangeField;
use App\Context\ClientPortal\Domain\Request\ClosureReason;
use App\Context\ClientPortal\Domain\Request\PetChangeRequest;
use App\Context\ClientPortal\Domain\Request\PetCondition;
use App\Context\ClientPortal\Domain\Request\PortalRequestId;
use App\Context\ClientPortal\Domain\Request\PrescriptionRenewalRequest;
use App\Context\ClientPortal\Domain\Request\ProposedChange;
use App\Context\ClientPortal\Domain\Request\RequestResolution;
use App\Context\ClientPortal\Domain\Request\RequestStatus;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\DbalClientLinkRepository;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\DbalPetChangeRequestRepository;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\DbalPortalRequestReadModel;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\DbalPrescriptionRenewalRequestRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** AC 234: both kinds in one list, scoped to the confirmed links, newest first. */
final class DbalPortalRequestReadModelTest extends KernelTestCase
{
    private const string CLINIC  = '01960000-0000-7000-8000-0000000a9101';
    private const string CLINIC2 = '01960000-0000-7000-8000-0000000a9102';
    private const string CLIENT  = '01960000-0000-7000-8000-0000000a9201';
    private const string REVOKED = '01960000-0000-7000-8000-0000000a9202';
    private const string USER    = '01960000-0000-7000-8000-0000000a9301';
    private const string USER2   = '01960000-0000-7000-8000-0000000a9302';
    private const string PET     = '01960000-0000-7000-8000-0000000a9401';
    private const string RX      = '01960000-0000-7000-8000-0000000a9501';
    private const string RESULT  = '01960000-0000-7000-8000-0000000a9502';
    private const string VET     = '01960000-0000-7000-8000-0000000a9601';

    // CLIENT in CLINIC for USER, newest first: R4 (renewal), C3 and C2 (same instant, higher id first), R1 (closed)
    private const string R1 = '01960000-0000-7000-8000-0000000a9001';
    private const string C2 = '01960000-0000-7000-8000-0000000a9002';
    private const string C3 = '01960000-0000-7000-8000-0000000a9003';
    private const string R4 = '01960000-0000-7000-8000-0000000a9004';
    // Never listed for USER in CLINIC: another account, a revoked link, another clinic
    private const string X1 = '01960000-0000-7000-8000-0000000a9011';
    private const string X2 = '01960000-0000-7000-8000-0000000a9012';
    private const string X3 = '01960000-0000-7000-8000-0000000a9013';

    private DbalPortalRequestReadModel $model;

    protected function setUp(): void
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $this->model = new DbalPortalRequestReadModel($connection);

        $now   = self::utc('2026-10-01 08:00:00');
        $links = new DbalClientLinkRepository($connection);
        $links->save(ClientLink::confirm(ClientLinkId::fromString('01960000-0000-7000-8000-0000000a9701'), self::USER, self::CLINIC, self::CLIENT, $now));
        $revoked = ClientLink::confirm(ClientLinkId::fromString('01960000-0000-7000-8000-0000000a9702'), self::USER, self::CLINIC, self::REVOKED, $now);
        $revoked->revoke($now);
        $links->save($revoked);
        $links->save(ClientLink::confirm(ClientLinkId::fromString('01960000-0000-7000-8000-0000000a9703'), self::USER, self::CLINIC2, self::CLIENT, $now));

        $changes  = new DbalPetChangeRequestRepository($connection);
        $renewals = new DbalPrescriptionRenewalRequestRepository($connection);

        $closed = self::renewal(self::R1, '2026-10-20 08:00:00');
        $closed->close(ClosureReason::PET_DECEASED, self::utc('2026-10-21 08:00:00'));
        $renewals->add($closed);
        $changes->save(self::change(self::C2, '2026-10-25 08:00:00'));
        $changes->save(self::change(self::C3, '2026-10-25 08:00:00'));
        $done = self::renewal(self::R4, '2026-10-26 08:00:00');
        $done->resolve(RequestResolution::of(RequestStatus::DONE, self::VET, 'Dr Claire Lambert', 'Prête.', self::utc('2026-10-27 08:00:00')), self::RESULT);
        $done->markClientUnreachable();
        $renewals->add($done);
        $changes->save(self::change(self::X1, '2026-10-28 08:00:00', user: self::USER2));
        $changes->save(self::change(self::X2, '2026-10-28 08:00:00', client: self::REVOKED));
        $changes->save(self::change(self::X3, '2026-10-28 08:00:00', clinic: self::CLINIC2));
    }

    public function testTheOwnerListsItsRequestsOfTheConfirmedLinksNewestFirst(): void
    {
        self::assertSame([self::R4, self::C3, self::C2, self::R1], $this->ids($this->model->ownerPage(self::USER, self::CLINIC, null, 10)));
        self::assertSame([self::C2, self::R1], $this->ids($this->model->ownerPage(self::USER, self::CLINIC, new RequestPosition(self::utc('2026-10-25 08:00:00'), self::C3), 10)));
        self::assertSame([self::R4], $this->ids($this->model->ownerPage(self::USER, self::CLINIC, null, 1)));
        self::assertSame([], $this->model->ownerPage(self::USER2, self::CLINIC2, null, 10));
    }

    public function testEveryFieldOfBothKinds(): void
    {
        [$renewal, $change] = $this->model->ownerPage(self::USER, self::CLINIC, null, 2);

        self::assertSame(
            [self::R4, 'prescription_renewal', self::CLINIC, self::CLIENT, self::USER, self::PET, null, null, self::RX, 'improved', 'Va mieux', 'done', '2026-10-26 08:00:00', '2026-10-27 08:00:00', 'Dr Claire Lambert', 'Prête.', self::RESULT, true, null],
            self::fields($renewal),
        );
        self::assertSame(
            [self::C3, 'pet_change', self::CLINIC, self::CLIENT, self::USER, self::PET, 'name', 'Mokka', null, null, 'Faute de frappe', 'submitted', '2026-10-25 08:00:00', null, null, null, null, false, null],
            self::fields($change),
        );
        self::assertSame('pet_deceased', $this->model->ownerPage(self::USER, self::CLINIC, null, 4)[3]->closedReason);
    }

    public function testTheClinicQueueByStatusAndKind(): void
    {
        self::assertSame([self::X2, self::X1, self::R4, self::C3, self::C2], $this->ids($this->model->clinicPage(self::CLINIC, ['submitted', 'done', 'rejected'], null, null, 10)));
        self::assertSame([self::R4], $this->ids($this->model->clinicPage(self::CLINIC, ['submitted', 'done'], 'prescription_renewal', null, 10)));
        self::assertSame([self::X2, self::X1, self::C3, self::C2], $this->ids($this->model->clinicPage(self::CLINIC, ['submitted'], 'pet_change', null, 10)));
        self::assertSame([self::C2], $this->ids($this->model->clinicPage(self::CLINIC, ['submitted'], null, new RequestPosition(self::utc('2026-10-25 08:00:00'), self::C3), 10)));
        self::assertSame([], $this->model->clinicPage(self::CLINIC, ['submitted'], 'questionnaire', null, 10));
    }

    /**
     * @param list<PortalRequestRecord> $records
     *
     * @return list<string>
     */
    private function ids(array $records): array
    {
        return array_map(static fn (PortalRequestRecord $r): string => $r->id, $records);
    }

    /**
     * @return list<mixed>
     */
    private static function fields(PortalRequestRecord $r): array
    {
        return [
            $r->id, $r->kind, $r->clinicId, $r->clientId, $r->userId, $r->petId, $r->field, $r->proposedValue, $r->prescriptionId, $r->petCondition, $r->note, $r->status,
            $r->submittedAt->format('Y-m-d H:i:s'), $r->resolvedAt?->format('Y-m-d H:i:s'), $r->resolvedByName, $r->resolutionMessage, $r->resultingPrescriptionId, $r->clientUnreachable, $r->closedReason,
        ];
    }

    private static function change(string $id, string $at, string $user = self::USER, string $client = self::CLIENT, string $clinic = self::CLINIC): PetChangeRequest
    {
        return PetChangeRequest::submit(
            PortalRequestId::fromString($id),
            $clinic,
            $client,
            $user,
            self::PET,
            ProposedChange::of(ChangeField::NAME, 'Mokka', null, 'Faute de frappe', self::utc($at)),
            'Moka',
            self::utc($at),
        );
    }

    private static function renewal(string $id, string $at): PrescriptionRenewalRequest
    {
        return PrescriptionRenewalRequest::submit(PortalRequestId::fromString($id), self::CLINIC, self::CLIENT, self::USER, self::RX, self::PET, PetCondition::IMPROVED, 'Va mieux', self::utc($at));
    }

    private static function utc(string $at): \DateTimeImmutable
    {
        return new \DateTimeImmutable($at, new \DateTimeZone('UTC'));
    }
}
