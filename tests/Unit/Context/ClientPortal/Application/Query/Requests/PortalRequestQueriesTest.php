<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Query\Requests;

use App\Context\ClientPortal\Application\Port\PetState;
use App\Context\ClientPortal\Application\Port\PortalRequestRecord;
use App\Context\ClientPortal\Application\Port\RecordedWeight;
use App\Context\ClientPortal\Application\Query\GetLatestRenewalStatuses\GetLatestRenewalStatuses;
use App\Context\ClientPortal\Application\Query\GetLatestRenewalStatuses\GetLatestRenewalStatusesHandler;
use App\Context\ClientPortal\Application\Query\GetPortalRequest\GetPortalRequest;
use App\Context\ClientPortal\Application\Query\ListClinicPortalRequests\ListClinicPortalRequests;
use App\Context\ClientPortal\Application\Query\ListOwnerPortalRequests\ListOwnerPortalRequests;
use App\Context\ClientPortal\Application\Service\RenewalStatuses;
use App\Context\ClientPortal\Application\Service\RequestCursor;
use App\Context\ClientPortal\Domain\Request\ChangeField;
use App\Context\ClientPortal\Domain\Request\ClosureReason;
use App\Context\ClientPortal\Domain\Request\RequestResolution;
use App\Context\ClientPortal\Domain\Request\RequestStatus;
use App\Shared\Application\Pagination\Cursor;
use App\Shared\Application\Pagination\InvalidCursorException;
use App\Tests\Unit\Context\ClientPortal\Support\PetWorld as P;
use App\Tests\Unit\Context\ClientPortal\Support\Requests;
use App\Tests\Unit\Context\ClientPortal\Support\RequestWorld as W;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/** AC 67, 69, 234, 238: the owner's list, the clinic's queue and detail, `renewalStatus`. */
final class PortalRequestQueriesTest extends TestCase
{
    private const string R1 = '01960000-0000-7000-8000-0000000e0001';
    private const string R2 = '01960000-0000-7000-8000-0000000e0002';
    private const string R3 = '01960000-0000-7000-8000-0000000e0003';

    public function testTheOwnerSeesItsRequestsNewestFirstInTheClinicTimeZoneWithANextCursor(): void
    {
        $world                 = new W();
        $world->fakes->records = [
            self::record(self::R3, 'prescription_renewal', P::PET, 'done', '2026-10-28 08:00:00', resolvedAt: '2026-10-28 10:00:00', message: 'Prête.', resulting: W::RESULTING),
            self::record(self::R2, 'pet_change', P::PET2, 'submitted', '2026-10-27 08:00:00'),
            self::record(self::R1, 'pet_change', P::PET, 'submitted', '2026-10-26 08:00:00'),
        ];

        $page = $world->ownerRequests()(new ListOwnerPortalRequests(P::USER, P::CLINIC, null, 2));

        self::assertSame(['owner:' . P::USER . ':' . P::CLINIC . ':-:3'], $world->fakes->pages);
        self::assertSame(RequestCursor::after(Requests::utc('2026-10-27 08:00:00'), self::R2), $page->nextCursor);
        [$renewal, $change] = $page->items;
        self::assertSame(
            [self::R3, 'prescription_renewal', P::PET, null, null, W::PRESCRIPTION, 'done', '2026-10-28T09:00:00+01:00', '2026-10-28T11:00:00+01:00', 'Prête.', W::RESULTING],
            [$renewal->id, $renewal->kind, $renewal->petId, $renewal->field, $renewal->proposedValue, $renewal->prescriptionId, $renewal->status, $renewal->submittedAt->format('c'), $renewal->resolvedAt?->format('c'), $renewal->resolutionMessage, $renewal->resultingPrescriptionId],
        );
        self::assertSame(['weight', '12,4', 'submitted', null], [$change->field, $change->proposedValue, $change->status, $change->resolvedAt]);

        $world->ownerRequests()(new ListOwnerPortalRequests(P::USER, P::CLINIC, $page->nextCursor, 2));
        self::assertContains('owner:' . P::USER . ':' . P::CLINIC . ':2026-10-27 08:00:00/' . self::R2 . ':3', $world->fakes->pages);
    }

    public function testAnOpenRequestOfAnArchivedOrDeceasedPetReadsClosedToTheOwner(): void
    {
        $world = new W();
        $world->petState(P::PET, 'deceased');
        $world->fakes->records = [self::record(self::R1, 'pet_change', P::PET, 'submitted', '2026-10-26 08:00:00'), self::record(self::R2, 'pet_change', P::PET, 'done', '2026-10-25 08:00:00')];

        $page = $world->ownerRequests()(new ListOwnerPortalRequests(P::USER, P::CLINIC));

        self::assertSame(['closed', 'done'], array_map(static fn ($r): string => $r->status, $page->items));
        self::assertNull($page->nextCursor);
    }

    public function testAnEmptyPageReadsNoPet(): void
    {
        $world = new W();

        self::assertSame([], $world->ownerRequests()(new ListOwnerPortalRequests(P::USER, P::CLINIC))->items);
        self::assertSame([], $world->clinicRequests()(new ListClinicPortalRequests(P::CLINIC))->items);
    }

    #[TestWith(['not-base64!'])]
    #[TestWith(['eyJ2IjoxLCJwIjp7InJzIjoiMjAyNi0xMC0yNyIsImlkIjoieCJ9fQ'])]
    public function testACursorOfAnotherShapeIsRejected(string $cursor): void
    {
        $this->expectException(InvalidCursorException::class);
        new W()->ownerRequests()(new ListOwnerPortalRequests(P::USER, P::CLINIC, $cursor));
    }

    public function testACursorWithAnInvalidInstantIsRejected(): void
    {
        $this->expectException(InvalidCursorException::class);
        RequestCursor::position(Cursor::fromPosition(['rs' => '2026-02-30 08:00:00', 'id' => self::R1])->encode());
    }

    public function testTheClinicQueueLeavesOutTheRequestsOfAnArchivedPetAndNamesPetsAndClients(): void
    {
        $world = new W();
        $world->contact();
        $world->petState(P::PET2, archived: true, name: 'Rex');
        $world->fakes->records = [
            self::record(self::R3, 'prescription_renewal', P::PET, 'submitted', '2026-10-28 08:00:00', unreachable: true),
            self::record(self::R2, 'pet_change', P::PET2, 'submitted', '2026-10-27 08:00:00'),
            self::record(self::R1, 'pet_change', P::PET2, 'done', '2026-10-26 08:00:00', clientId: P::CLIENT2),
        ];

        $page = $world->clinicRequests()(new ListClinicPortalRequests(P::CLINIC, null, null, null, 3));

        self::assertSame(['clinic:' . P::CLINIC . ':submitted,done,rejected:-:-:4'], $world->fakes->pages);
        self::assertSame([self::R3, self::R1], array_map(static fn ($r): string => $r->id, $page->items));
        [$renewal, $done] = $page->items;
        self::assertSame(
            ['prescription_renewal', P::CLIENT, 'Sophie Martin', 'Moka', 'improved', 'Va mieux', 'submitted', '2026-10-28T09:00:00+01:00', true],
            [$renewal->kind, $renewal->clientId, $renewal->clientName, $renewal->petName, $renewal->petCondition, $renewal->note, $renewal->status, $renewal->submittedAt->format('c'), $renewal->clientUnreachable],
        );
        self::assertSame([null, 'Rex', 'weight', 'done'], [$done->clientName, $done->petName, $done->field, $done->status]);
    }

    public function testTheClinicQueueFiltersByStatusAndKindAndRefusesClosedOrUnknownOnes(): void
    {
        $world                 = new W();
        $world->fakes->records = [self::record(self::R1, 'pet_change', P::PET, 'submitted', '2026-10-26 08:00:00'), self::record(self::R2, 'pet_change', P::PET, 'submitted', '2026-10-25 08:00:00')];

        $page = $world->clinicRequests()(new ListClinicPortalRequests(P::CLINIC, 'submitted', 'pet_change', null, 1));
        self::assertSame('clinic:' . P::CLINIC . ':submitted:pet_change:-:2', $world->fakes->pages[0]);
        self::assertSame(RequestCursor::after(Requests::utc('2026-10-26 08:00:00'), self::R1), $page->nextCursor);

        foreach ([['closed', null], [null, 'questionnaire']] as [$status, $kind]) {
            self::assertSame([], $world->clinicRequests()(new ListClinicPortalRequests(P::CLINIC, $status, $kind))->items);
        }
        self::assertCount(1, $world->fakes->pages);
    }

    public function testAChangeRequestIsDetailedWithItsThreeValues(): void
    {
        $world = new W();
        $world->contact();
        $world->changes->save(Requests::change(self::R1));
        $world->pets->fakes->recordedWeights[P::PET] = new RecordedWeight(12000, new \DateTimeImmutable('2026-10-28'));

        $details = $world->getRequest()(new GetPortalRequest(P::CLINIC, self::R1));

        self::assertNotNull($details);
        self::assertSame(
            [self::R1, 'pet_change', P::CLIENT, 'Sophie Martin', P::USER, P::PET, 'Moka', 'weight', '12,4', ['weightGrams' => 12400, 'measuredOn' => '2026-10-28'], '11800', '12000', null, null, 'Pesé à la maison', 'submitted', null, '2026-10-28T09:00:00+01:00', null, null, null, null, null, false],
            [
                $details->id, $details->kind, $details->clientId, $details->clientName, $details->userId, $details->petId, $details->petName, $details->field,
                $details->proposedValue, $details->normalisedValue, $details->currentValueAtSubmit, $details->currentValue, $details->prescriptionId,
                $details->petCondition, $details->note, $details->status, $details->closedReason, $details->submittedAt->format('c'), $details->resolvedAt,
                $details->resolvedByName, $details->resolutionMessage, $details->resultingPrescriptionId, $details->notificationMessageId, $details->clientUnreachable,
            ],
        );
    }

    public function testEachFieldDetailsThePetsCurrentValue(): void
    {
        $world                               = new W();
        $world->pets->fakes->states[P::PET2] = new PetState(P::PET2, 'Rex', P::CLIENT, 'intact', '250269604123456', 'alive', false);
        $values                              = ['name' => 'Rexy', 'weight' => '4', 'sterilization' => 'neutered', 'chip_number' => '250269604999999', 'other' => ''];
        $current                             = [];

        foreach (ChangeField::cases() as $i => $field) {
            $id = \sprintf('01960000-0000-7000-8000-0000000e01%02d', $i);
            $world->changes->save(Requests::change($id, petId: P::PET2, field: $field, value: $values[$field->value]));
            $current[] = $world->getRequest()(new GetPortalRequest(P::CLINIC, $id))?->currentValue;
        }

        self::assertSame(['Rex', null, 'intact', '250269604123456', null], $current);
    }

    public function testAProcessedRenewalIsDetailedWithItsAnswerAndAClosedOneWithItsReason(): void
    {
        $world   = new W();
        $renewal = Requests::renewal(self::R2, W::PRESCRIPTION);
        $renewal->resolve(RequestResolution::of(RequestStatus::DONE, W::VET, 'Dr Claire Lambert', 'Prête.', Requests::utc('2026-10-28 10:00:00')), W::RESULTING);
        $renewal->attachNotification('01960000-0000-7000-8000-0000000f0001');
        $world->renewals->save($renewal);
        $closed = Requests::renewal(self::R3, W::RESULTING);
        $closed->close(ClosureReason::PRESCRIPTION_CANCELLED, Requests::utc('2026-10-28 10:00:00'));
        $world->renewals->save($closed);

        $details = $world->getRequest()(new GetPortalRequest(P::CLINIC, self::R2));
        self::assertSame(
            ['prescription_renewal', null, [], null, null, W::PRESCRIPTION, 'improved', 'Va mieux', 'done', '2026-10-28T11:00:00+01:00', 'Dr Claire Lambert', 'Prête.', W::RESULTING, '01960000-0000-7000-8000-0000000f0001'],
            [$details?->kind, $details?->field, $details?->normalisedValue, $details?->currentValueAtSubmit, $details?->currentValue, $details?->prescriptionId, $details?->petCondition, $details?->note, $details?->status, $details?->resolvedAt?->format('c'), $details?->resolvedByName, $details?->resolutionMessage, $details?->resultingPrescriptionId, $details?->notificationMessageId],
        );
        self::assertSame(['closed', 'prescription_cancelled'], [$world->getRequest()(new GetPortalRequest(P::CLINIC, self::R3))?->status, $world->getRequest()(new GetPortalRequest(P::CLINIC, self::R3))?->closedReason]);
    }

    public function testAnOpenRequestOfADeceasedPetIsDetailedAsClosed(): void
    {
        $world = new W();
        $world->changes->save(Requests::change(self::R1));
        $world->petState(P::PET, 'deceased');

        $details = $world->getRequest()(new GetPortalRequest(P::CLINIC, self::R1));

        self::assertSame(['closed', 'pet_deceased', null], [$details?->status, $details?->closedReason, $details?->clientName]);
    }

    public function testAnUnknownRequestIsNull(): void
    {
        self::assertNull(new W()->getRequest()(new GetPortalRequest(P::CLINIC, self::R1)));
    }

    public function testTheLatestRenewalOfEachPrescriptionIsItsStatus(): void
    {
        $world = new W();
        $old   = Requests::renewal(self::R1, W::PRESCRIPTION, at: '2026-10-01 08:00:00');
        $old->resolve(RequestResolution::of(RequestStatus::REJECTED, W::VET, 'Dr Claire Lambert', null, Requests::utc('2026-10-02 08:00:00')));
        $world->renewals->save($old);
        $world->renewals->save(Requests::renewal(self::R2, W::PRESCRIPTION, at: '2026-10-20 08:00:00'));

        $statuses = new GetLatestRenewalStatusesHandler(new RenewalStatuses($world->renewals))(new GetLatestRenewalStatuses(P::CLINIC, [W::PRESCRIPTION, W::RESULTING]));

        self::assertSame([W::PRESCRIPTION], array_keys($statuses));
        self::assertSame(['submitted', self::R2, null], [$statuses[W::PRESCRIPTION]->status, $statuses[W::PRESCRIPTION]->requestId, $statuses[W::PRESCRIPTION]->resultingPrescriptionId]);
    }

    private static function record(
        string $id,
        string $kind,
        string $petId,
        string $status,
        string $submittedAt,
        ?string $resolvedAt = null,
        ?string $message = null,
        ?string $resulting = null,
        bool $unreachable = false,
        string $clientId = P::CLIENT,
    ): PortalRequestRecord {
        $renewal = 'prescription_renewal' === $kind;

        return new PortalRequestRecord(
            $id,
            $kind,
            P::CLINIC,
            $clientId,
            P::USER,
            $petId,
            $renewal ? null : 'weight',
            $renewal ? null : '12,4',
            $renewal ? W::PRESCRIPTION : null,
            $renewal ? 'improved' : null,
            $renewal ? 'Va mieux' : null,
            $status,
            Requests::utc($submittedAt),
            null === $resolvedAt ? null : Requests::utc($resolvedAt),
            null === $resolvedAt ? null : 'Dr Claire Lambert',
            $message,
            $resulting,
            $unreachable,
            null,
        );
    }
}
