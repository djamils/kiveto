<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Query\Appointments;

use App\Context\ClientPortal\Application\Port\AppointmentScope;
use App\Context\ClientPortal\Application\Port\CareBooking;
use App\Context\ClientPortal\Application\Port\OnlineMotif;
use App\Context\ClientPortal\Application\Port\OwnerAppointment;
use App\Context\ClientPortal\Application\Query\GetOwnerAppointmentDetail\GetOwnerAppointmentDetail;
use App\Context\ClientPortal\Application\Query\GetOwnerAppointmentDetail\GetOwnerAppointmentDetailHandler;
use App\Context\ClientPortal\Application\Query\ListBookableMotifs\BookableMotif;
use App\Context\ClientPortal\Application\Query\ListBookableMotifs\ListBookableMotifs;
use App\Context\ClientPortal\Application\Query\ListBookableMotifs\ListBookableMotifsHandler;
use App\Context\ClientPortal\Application\Query\ListOwnerAppointments\ListOwnerAppointments;
use App\Context\ClientPortal\Application\Query\ListOwnerAppointments\ListOwnerAppointmentsHandler;
use App\Context\ClientPortal\Application\Service\PortalAppointments;
use App\Context\ClientPortal\Domain\Exception\PetNotFoundException;
use App\Context\ClientPortal\Domain\Request\ClosureReason;
use App\Tests\Unit\Context\ClientPortal\Support\InMemoryQuestionnaires;
use App\Tests\Unit\Context\ClientPortal\Support\PetWorld as W;
use App\Tests\Unit\Context\ClientPortal\Support\Requests;
use PHPUnit\Framework\TestCase;

/**
 * The account's appointments (S4i) and its bookable motifs: the account is
 * linked to CLIENT, sees PET (Moka) and PET2 (Rex), not PET3 (CLIENT2's).
 * The clinic is in Europe/Paris, the clock at 2026-10-29 09:00 UTC.
 */
final class OwnerAppointmentQueriesTest extends TestCase
{
    private const string VET       = '01960000-0000-7000-8000-00000000d001';
    private const string ASV       = '01960000-0000-7000-8000-00000000d002';
    private const string VACCIN    = '01960000-0000-7000-8000-00000000b001';
    private const string VERMIFUGE = '01960000-0000-7000-8000-00000000b002';
    private const string FRONTLINE = '01960000-0000-7000-8000-00000000b003';
    private const string SOIN      = '01960000-0000-7000-8000-00000000b004';
    private const string CONSULT   = '01960000-0000-7000-8000-00000000b005';
    private const string A1        = '01960000-0000-7000-8000-000000000101';
    private const string A2        = '01960000-0000-7000-8000-000000000102';
    private const string A3        = '01960000-0000-7000-8000-000000000103';
    private const string A4        = '01960000-0000-7000-8000-000000000104';

    private InMemoryQuestionnaires $questionnaires;

    public function testThePageIsOfTheLinkedClientsAndTheirPetsInTheClinicsTimeZone(): void
    {
        $world                      = $this->world();
        $world->fakes->nextCursor   = 'next';
        $world->fakes->appointments = [
            self::appointment(self::A1, W::PET, motifId: self::VACCIN, motifLabel: 'Rappel vaccin', kind: 'vaccination', cancellableUntil: '2026-11-04 09:00:00'),
            self::appointment(self::A2, null, practitioner: self::ASV, status: 'pending_clinic_confirmation', source: 'portal', pending: true),
            self::appointment(self::A3, W::PET3, status: 'cancelled_by_client', occupies: false),
        ];
        $world->act(W::PET, 'Rage', '2025-11-10', '2026-11-10');
        $world->fakes->careBookings = [new CareBooking(W::PET, new \DateTimeImmutable('2026-11-05 09:00:00', new \DateTimeZone('UTC')), 'vaccination')];
        // A2 has a submitted questionnaire; A1's was closed: it reads not started
        $this->questionnaires->save(Requests::questionnaire('01960000-0000-7000-8000-0000000f9001', self::A2));
        $closed = Requests::questionnaire('01960000-0000-7000-8000-0000000f9002', self::A1);
        $closed->close(ClosureReason::APPOINTMENT_CANCELLED, Requests::utc('2026-10-28 09:00:00'));
        $this->questionnaires->save($closed);

        $page = $this->listHandler($world)(new ListOwnerAppointments(W::USER, W::CLINIC, AppointmentScope::UPCOMING, 'cursor', 10));

        self::assertSame('next', $page->nextCursor);
        self::assertContains(\sprintf('page:%s:%s:%s:upcoming:cursor:10:2026-10-29T09:00:00+00:00', W::CLINIC, W::CLIENT, W::PET . ',' . W::PET2), $world->fakes->calls);
        self::assertContains('namesOf:' . W::CLINIC . ':' . self::VET . ',' . self::ASV, $world->fakes->calls);

        [$a1, $a2, $a3] = $page->items;
        self::assertSame(
            [self::A1, '2026-11-05T10:00:00+01:00', '2026-11-05T10:30:00+01:00', W::PET, 'Moka', self::VACCIN, 'Rappel vaccin', self::VET, 'Dr Claire Lambert', 'confirmed', true, false, 'clinic', '2026-11-04T10:00:00+01:00', 'not_started'],
            [$a1->id, $a1->startAt->format('c'), $a1->endAt->format('c'), $a1->petId, $a1->petName, $a1->motifId, $a1->motifLabel, $a1->practitionerId, $a1->practitionerName, $a1->status, $a1->occupiesSlot, $a1->isPending, $a1->source, $a1->cancellableUntil?->format('c'), $a1->questionnaireStatus],
        );
        self::assertSame(['vaccination', 'Rage', '2026-11-10', 'booked'], [$a1->relatedCareItem?->kind, $a1->relatedCareItem?->label, $a1->relatedCareItem?->dueOn->format('Y-m-d'), $a1->relatedCareItem?->status->value]);
        // No animal, no motif: « Rendez-vous », an unnamed practitioner.
        self::assertSame([null, null, null, 'Rendez-vous', null, 'pending_clinic_confirmation', true, 'portal', null, null, 'submitted'], [$a2->petId, $a2->petName, $a2->motifId, $a2->motifLabel, $a2->practitionerName, $a2->status, $a2->isPending, $a2->source, $a2->cancellableUntil, $a2->relatedCareItem, $a2->questionnaireStatus]);
        // An animal the account does not see is not named.
        self::assertSame([null, null, 'cancelled_by_client', false], [$a3->petId, $a3->petName, $a3->status, $a3->occupiesSlot]);
    }

    public function testAMotifWithoutCareItemOfItsKindHasNoRelatedItemAndAPageWithoutCareMotifReadsNoCare(): void
    {
        $world                      = $this->world();
        $world->fakes->appointments = [self::appointment(self::A1, W::PET, motifId: self::VERMIFUGE, motifLabel: 'Vermifuge', kind: 'internal_parasite')];
        $world->act(W::PET, 'Rage', '2025-11-10', '2026-11-10');

        self::assertNull($this->listHandler($world)(new ListOwnerAppointments(W::USER, W::CLINIC, AppointmentScope::PAST))->items[0]->relatedCareItem);

        $world                      = $this->world();
        $world->fakes->appointments = [self::appointment(self::A1, W::PET, motifId: self::CONSULT, motifLabel: 'Consultation')];

        self::assertNull($this->listHandler($world)(new ListOwnerAppointments(W::USER, W::CLINIC, AppointmentScope::PAST))->items[0]->relatedCareItem);
        self::assertSame([], array_values(array_filter($world->fakes->calls, static fn (string $call): bool => str_starts_with($call, 'latestActs:'))));
    }

    public function testAnEmptyPageNamesNobody(): void
    {
        $world = $this->world();

        $page = $this->listHandler($world)(new ListOwnerAppointments(W::USER, W::CLINIC, AppointmentScope::PAST));

        self::assertSame([], $page->items);
        self::assertNull($page->nextCursor);
        self::assertSame([], array_values(array_filter($world->fakes->calls, static fn (string $call): bool => str_starts_with($call, 'namesOf:'))));
    }

    public function testTheDetailIsOnlyTheAccountsAppointment(): void
    {
        $world                      = $this->world();
        $world->fakes->appointments = [
            self::appointment(self::A1, W::PET, owner: W::CLIENT2),
            self::appointment(self::A2, null, owner: W::CLIENT),
            self::appointment(self::A3, W::PET3, owner: W::CLIENT2),
            self::appointment(self::A4, null, owner: null),
        ];
        $handler = $this->detailHandler($world);

        self::assertSame('Moka', $handler(new GetOwnerAppointmentDetail(W::USER, W::CLINIC, self::A1))?->petName, 'one of its pets');
        self::assertSame(self::A2, $handler(new GetOwnerAppointmentDetail(W::USER, W::CLINIC, self::A2))?->id, 'one of its client files');
        self::assertNull($handler(new GetOwnerAppointmentDetail(W::USER, W::CLINIC, self::A3)), 'another client and pet');
        self::assertNull($handler(new GetOwnerAppointmentDetail(W::USER, W::CLINIC, self::A4)), 'nobody');
        self::assertNull($handler(new GetOwnerAppointmentDetail(W::USER, W::CLINIC, '01960000-0000-7000-8000-000000000999')), 'unknown');
    }

    public function testMotifsAreSuggestedByTheDueCareOfThePet(): void
    {
        $world                = $this->world();
        $world->fakes->motifs = [
            new OnlineMotif(self::CONSULT, 'Consultation', 'Examen général', 30, null),
            new OnlineMotif(self::VACCIN, 'Rappel vaccin', null, 15, 'vaccination'),
            new OnlineMotif(self::VERMIFUGE, 'Vermifuge', null, 15, 'internal_parasite'),
            new OnlineMotif(self::FRONTLINE, 'Antiparasitaire', null, 15, 'external_parasite'),
            new OnlineMotif(self::SOIN, 'Prévention', null, 15, 'other'),
        ];
        // Moka: a vaccination due in 20 days and another overdue, a dewormer due soon,
        // an external treatment up to date, another act booked.
        $world->act(W::PET, 'Rage', '2025-11-18', '2026-11-18');
        $world->act(W::PET, 'Typhus', '2025-10-01', '2026-10-01');
        $world->act(W::PET, 'Milbemax', '2026-09-01', '2026-11-08', 'internal_parasite');
        $world->act(W::PET, 'Frontline', '2026-09-01', '2027-03-01', 'external_parasite');
        $world->act(W::PET, 'Bilan', '2025-10-01', '2026-10-20', 'other');
        $world->fakes->careBookings = [new CareBooking(W::PET, new \DateTimeImmutable('2026-11-02 09:00:00', new \DateTimeZone('UTC')), 'other')];
        // Rex: only a vaccination due in 20 days.
        $world->act(W::PET2, 'Rage', '2025-11-18', '2026-11-18');

        $moka = $this->motifsHandler($world)(new ListBookableMotifs(W::USER, W::CLINIC, W::PET));

        self::assertSame([
            [self::CONSULT, 'Consultation', 'Examen général', 30, false, null],
            [self::VACCIN, 'Rappel vaccin', null, 15, true, 'Rappel de vaccin en retard'],
            [self::VERMIFUGE, 'Vermifuge', null, 15, true, 'Vermifuge à prévoir'],
            [self::FRONTLINE, 'Antiparasitaire', null, 15, false, null],
            [self::SOIN, 'Prévention', null, 15, false, null],
        ], array_map(static fn (BookableMotif $m): array => [$m->id, $m->label, $m->description, $m->durationMin, $m->suggested, $m->suggestionReason], $moka));

        $rex = $this->motifsHandler($world)(new ListBookableMotifs(W::USER, W::CLINIC, W::PET2));
        self::assertSame([null, 'Rappel de vaccin à prévoir', null, null, null], array_map(static fn (BookableMotif $m): ?string => $m->suggestionReason, $rex));

        $none = $this->motifsHandler($world)(new ListBookableMotifs(W::USER, W::CLINIC));
        self::assertSame([false, false, false, false, false], array_map(static fn (BookableMotif $m): bool => $m->suggested, $none));
    }

    public function testEveryCareKindHasItsSuggestionReason(): void
    {
        $world                = $this->world();
        $world->fakes->motifs = [
            new OnlineMotif(self::FRONTLINE, 'Antiparasitaire', null, 15, 'external_parasite'),
            new OnlineMotif(self::SOIN, 'Prévention', null, 15, 'other'),
            new OnlineMotif(self::CONSULT, 'Autre', null, 15, 'unknown_kind'),
        ];
        $world->act(W::PET, 'Frontline', '2026-09-01', '2026-10-01', 'external_parasite');
        $world->act(W::PET, 'Bilan', '2025-10-01', '2026-11-20', 'other');
        $world->act(W::PET, 'Inconnu', '2025-10-01', '2026-11-20', 'unknown_kind');

        self::assertSame(
            ['Traitement antiparasitaire en retard', 'Soin de prévention à prévoir', 'Soin de prévention à prévoir'],
            array_map(static fn (BookableMotif $m): ?string => $m->suggestionReason, $this->motifsHandler($world)(new ListBookableMotifs(W::USER, W::CLINIC, W::PET))),
        );
    }

    public function testMotifsOfAPetTheAccountDoesNotSeeAreRefused(): void
    {
        $world = $this->world();

        $this->expectException(PetNotFoundException::class);
        $this->motifsHandler($world)(new ListBookableMotifs(W::USER, W::CLINIC, W::PET3));
    }

    private function world(): W
    {
        $this->questionnaires = new InMemoryQuestionnaires();
        $world                = new W();
        $world->pet();
        $world->pet(W::PET2, 'Rex');
        $world->pet(W::PET3, 'Filou', 'alive', W::CLIENT2);
        $world->fakes->practitioners = [self::VET => 'Dr Claire Lambert'];

        return $world;
    }

    private static function appointment(
        string $id,
        ?string $animalId,
        ?string $owner = W::CLIENT,
        string $practitioner = self::VET,
        string $status = 'confirmed',
        bool $occupies = true,
        bool $pending = false,
        string $source = 'clinic',
        ?string $motifId = null,
        ?string $motifLabel = null,
        ?string $kind = null,
        ?string $cancellableUntil = null,
    ): OwnerAppointment {
        $utc = new \DateTimeZone('UTC');

        return new OwnerAppointment(
            $id,
            $owner,
            $animalId,
            $practitioner,
            new \DateTimeImmutable('2026-11-05 09:00:00', $utc),
            30,
            $status,
            $occupies,
            $pending,
            $source,
            $motifId,
            $motifLabel,
            $kind,
            new \DateTimeImmutable('2026-10-01 08:00:00', $utc),
            null === $cancellableUntil ? null : new \DateTimeImmutable($cancellableUntil, $utc),
        );
    }

    private function views(W $world): PortalAppointments
    {
        return new PortalAppointments($world->careItems(), $world->fakes, $world->documents->fakes, $this->questionnaires);
    }

    private function listHandler(W $world): ListOwnerAppointmentsHandler
    {
        return new ListOwnerAppointmentsHandler($world->ownerPets(), $world->fakes, $this->views($world), $world->documents->clock);
    }

    private function detailHandler(W $world): GetOwnerAppointmentDetailHandler
    {
        return new GetOwnerAppointmentDetailHandler($world->ownerPets(), $world->fakes, $this->views($world));
    }

    private function motifsHandler(W $world): ListBookableMotifsHandler
    {
        return new ListBookableMotifsHandler($world->ownerPets(), $world->careItems(), $world->fakes);
    }
}
