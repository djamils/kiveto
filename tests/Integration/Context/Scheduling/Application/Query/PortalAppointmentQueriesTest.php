<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Application\Query;

use App\Context\Scheduling\Application\Query\GetOwnerAppointment\GetOwnerAppointment;
use App\Context\Scheduling\Application\Query\GetOwnerAppointment\GetOwnerAppointmentHandler;
use App\Context\Scheduling\Application\Query\ListAppointmentsForOwners\AppointmentListScope;
use App\Context\Scheduling\Application\Query\ListAppointmentsForOwners\ListAppointmentsForOwners;
use App\Context\Scheduling\Application\Query\ListAppointmentsForOwners\ListAppointmentsForOwnersHandler;
use App\Context\Scheduling\Application\Query\ListAppointmentsForOwners\PortalAppointmentItem;
use App\Context\Scheduling\Application\Query\ListCareBookingsForAnimals\CareBookingItem;
use App\Context\Scheduling\Application\Query\ListCareBookingsForAnimals\ListCareBookingsForAnimals;
use App\Context\Scheduling\Application\Query\ListCareBookingsForAnimals\ListCareBookingsForAnimalsHandler;
use App\Context\Scheduling\Domain\Booking\BookingMotif;
use App\Context\Scheduling\Domain\Booking\BookingMotifId;
use App\Context\Scheduling\Domain\Booking\BookingMotifRepositoryInterface;
use App\Context\Scheduling\Domain\Booking\BookingPreventiveCareKind;
use App\Context\Scheduling\Domain\Booking\ConfirmationMode;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettings;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettingsRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentSource;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\CancellationActor;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity\AppointmentEntity;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use App\Shared\Application\Pagination\Cursor;
use App\Shared\Application\Pagination\CursorPage;
use App\Shared\Application\Pagination\InvalidCursorException;
use App\Tests\Shared\Time\FrozenClock;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * The Portal's appointment reads (S4i, S4f): never the reason nor the notes,
 * keyset paging on `(starts_at_utc, id)`, the owner's status and the end of
 * the cancellation window.
 */
final class PortalAppointmentQueriesTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC       = '01960000-0000-7000-8000-00000000c001';
    private const string OTHER_CLINIC = '01960000-0000-7000-8000-00000000c002';
    private const string OWNER        = '01960000-0000-7000-8000-00000000e001';
    private const string OTHER_OWNER  = '01960000-0000-7000-8000-00000000e002';
    private const string PET          = '01960000-0000-7000-8000-00000000a001';
    private const string PET2         = '01960000-0000-7000-8000-00000000a002';
    private const string STRANGER     = '01960000-0000-7000-8000-00000000a009';
    private const string VET          = '01960000-0000-7000-8000-00000000d001';
    private const string VACCIN       = '01960000-0000-7000-8000-00000000b001';
    private const string CONSULT      = '01960000-0000-7000-8000-00000000b002';
    private const string NOW          = '2026-11-02 09:00:00';

    private const string U1 = '01960000-0000-7000-8000-000000000101';
    private const string U2 = '01960000-0000-7000-8000-000000000102';
    private const string U3 = '01960000-0000-7000-8000-000000000103';
    private const string P1 = '01960000-0000-7000-8000-000000000201';
    private const string P2 = '01960000-0000-7000-8000-000000000202';
    private const string P3 = '01960000-0000-7000-8000-000000000203';
    private const string P4 = '01960000-0000-7000-8000-000000000204';

    public function testTheColumnsNeverIncludeTheReasonNorTheNotes(): void
    {
        self::assertDoesNotMatchRegularExpression('/\b(reason|notes)\b/', PortalAppointmentItem::COLUMNS . PortalAppointmentItem::MOTIF_JOIN);
        self::assertSame([], array_values(array_intersect(['reason', 'notes'], array_keys(get_class_vars(PortalAppointmentItem::class)))));
    }

    public function testUpcomingHoldsTheSlotAndStartsNowOrLaterSoonestFirst(): void
    {
        $this->world();

        $page = $this->list(AppointmentListScope::UPCOMING);

        self::assertSame([self::U2, self::U1, self::U3], self::ids($page));
        self::assertNull($page->nextCursor);

        [$u2, $u1, $u3] = $page->items;
        self::assertSame(
            [self::U1, self::OWNER, self::PET, self::VET, '2026-11-05 10:00', 30, 'confirmed', true, false, 'clinic', null, self::VACCIN, 'Rappel vaccin', 'vaccination', '2026-10-20 08:00', '2026-11-03 10:00'],
            [$u1->id, $u1->ownerId, $u1->animalId, $u1->practitionerUserId, $u1->startsAtUtc->format('Y-m-d H:i'), $u1->durationMinutes, $u1->status, $u1->occupiesSlot, $u1->isPending, $u1->source, $u1->cancelledBy, $u1->motifId, $u1->motifLabel, $u1->preventiveCareKind, $u1->createdAtUtc->format('Y-m-d H:i'), $u1->cancellableUntil?->format('Y-m-d H:i')],
        );
        // Booked inside the 48 h window: undoable for 15 minutes.
        self::assertSame(
            [self::OTHER_OWNER, self::PET2, 'pending_clinic_confirmation', true, 'portal', self::CONSULT, 'Consultation', null, '2026-11-02 09:05'],
            [$u2->ownerId, $u2->animalId, $u2->status, $u2->isPending, $u2->source, $u2->motifId, $u2->motifLabel, $u2->preventiveCareKind, $u2->cancellableUntil?->format('Y-m-d H:i')],
        );
        self::assertSame([null, null, null, null], [$u3->animalId, $u3->motifId, $u3->motifLabel, $u3->preventiveCareKind]);
    }

    public function testPastIsEveryOtherAppointmentLatestFirst(): void
    {
        $this->world();

        $page = $this->list(AppointmentListScope::PAST);

        self::assertSame([self::P1, self::P3, self::P2, self::P4], self::ids($page));
        self::assertSame(
            [['cancelled_by_client', 'client', false], ['confirmed', null, true], ['completed', null, false], ['declined', null, false]],
            array_map(static fn (PortalAppointmentItem $i): array => [$i->status, $i->cancelledBy, $i->occupiesSlot], $page->items),
        );
        self::assertSame([null, null, null, null], array_map(static fn (PortalAppointmentItem $i): ?\DateTimeImmutable => $i->cancellableUntil, $page->items));
    }

    public function testPagesFollowTheKeysetInBothScopes(): void
    {
        $this->world();

        $first = $this->list(AppointmentListScope::UPCOMING, limit: 2);
        self::assertSame([self::U2, self::U1], self::ids($first));
        self::assertNotNull($first->nextCursor);
        $second = $this->list(AppointmentListScope::UPCOMING, $first->nextCursor, 2);
        self::assertSame([self::U3], self::ids($second));
        self::assertNull($second->nextCursor);

        $first = $this->list(AppointmentListScope::PAST, limit: 2);
        self::assertSame([self::P1, self::P3], self::ids($first));
        self::assertNotNull($first->nextCursor);
        $second = $this->list(AppointmentListScope::PAST, $first->nextCursor, 2);
        self::assertSame([self::P2, self::P4], self::ids($second));
        self::assertNull($second->nextCursor);

        self::assertSame([self::U2], self::ids($this->list(AppointmentListScope::UPCOMING, limit: 0)));
    }

    public function testTheClientsAndTheAnimalsAreEachEnough(): void
    {
        $this->world();

        self::assertSame([self::U1, self::U3], self::ids($this->list(AppointmentListScope::UPCOMING, owners: [self::OWNER], animals: [])));
        self::assertSame([self::U2], self::ids($this->list(AppointmentListScope::UPCOMING, owners: [], animals: [self::PET2])));
        self::assertSame([], self::ids($this->list(AppointmentListScope::UPCOMING, owners: [], animals: [])));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideACursorOfAnotherShapeIsRefusedCases(): iterable
    {
        yield 'garbage' => ['!!'];
        yield 'another scope' => [Cursor::fromPosition(['s' => 'past', 'at' => '2026-11-05 10:00:00', 'id' => self::U1])->encode()];
        yield 'no scope' => [Cursor::fromPosition(['at' => '2026-11-05 10:00:00', 'id' => self::U1])->encode()];
        yield 'not an instant' => [Cursor::fromPosition(['s' => 'upcoming', 'at' => '2026-11-31 10:00:00', 'id' => self::U1])->encode()];
        yield 'not a uuid' => [Cursor::fromPosition(['s' => 'upcoming', 'at' => '2026-11-05 10:00:00', 'id' => 'x'])->encode()];
        yield 'an int date' => [Cursor::fromPosition(['s' => 'upcoming', 'at' => 20261105, 'id' => self::U1])->encode()];
    }

    #[DataProvider('provideACursorOfAnotherShapeIsRefusedCases')]
    public function testACursorOfAnotherShapeIsRefused(string $cursor): void
    {
        $this->expectException(InvalidCursorException::class);

        $this->list(AppointmentListScope::UPCOMING, $cursor, owners: [], animals: []);
    }

    public function testOneAppointmentIsReadForItsOwner(): void
    {
        $this->world();

        $item = $this->get(self::U1);
        self::assertNotNull($item);
        self::assertSame(['confirmed', self::VACCIN, 'Rappel vaccin', 'vaccination', '2026-11-03 10:00'], [$item->status, $item->motifId, $item->motifLabel, $item->preventiveCareKind, $item->cancellableUntil?->format('Y-m-d H:i')]);

        $cancelled = $this->get(self::P1);
        self::assertNotNull($cancelled);
        self::assertSame(['cancelled_by_client', null], [$cancelled->status, $cancelled->cancellableUntil]);
        self::assertNull($this->get('01960000-0000-7000-8000-000000000999'), 'unknown');
        self::assertNull($this->get('not-a-uuid'));
        self::assertNull($this->get(self::U1, self::OTHER_CLINIC), 'another clinic');
    }

    public function testCareBookingsAreTheHeldAppointmentsOfAMotifWithACareKind(): void
    {
        $this->world();
        // A held appointment of the vaccination motif for PET2, earlier than `from`, then cancelled.
        $this->appointment('01960000-0000-7000-8000-000000000301', self::OWNER, self::PET2, '2026-11-01 08:00:00', motifId: self::VACCIN);
        $this->appointment('01960000-0000-7000-8000-000000000302', self::OWNER, self::PET2, '2026-11-08 08:00:00', AppointmentStatus::CANCELLED, motifId: self::VACCIN);
        $this->appointment('01960000-0000-7000-8000-000000000303', self::OWNER, self::PET2, '2026-11-09 08:00:00', AppointmentStatus::PENDING_CLINIC_CONFIRMATION, motifId: self::VACCIN);

        $bookings = $this->careBookings([self::PET, self::PET2], '2026-11-01 23:00:00');

        self::assertSame(
            [[self::PET, '2026-11-05 10:00', 'vaccination'], [self::PET2, '2026-11-09 08:00', 'vaccination']],
            array_map(static fn (CareBookingItem $b): array => [$b->animalId, $b->startsAtUtc->format('Y-m-d H:i'), $b->preventiveCareKind], $bookings),
        );
        self::assertSame([], $this->careBookings([], '2026-11-01 23:00:00'));
    }

    private function world(): void
    {
        $this->motif(self::VACCIN, 'Rappel vaccin', BookingPreventiveCareKind::VACCINATION);
        $this->motif(self::CONSULT, 'Consultation', null);

        $settings = self::getContainer()->get(OnlineBookingSettingsRepositoryInterface::class);
        \assert($settings instanceof OnlineBookingSettingsRepositoryInterface);
        $settings->save(new OnlineBookingSettings(ClinicId::fromString(self::CLINIC), true, 15, 120, 24, 60, 48, false));

        $this->appointment(self::U1, self::OWNER, self::PET, '2026-11-05 10:00:00', motifId: self::VACCIN, createdAt: '2026-10-20 08:00:00');
        $this->appointment(self::U2, self::OTHER_OWNER, self::PET2, '2026-11-03 08:00:00', AppointmentStatus::PENDING_CLINIC_CONFIRMATION, motifId: self::CONSULT, createdAt: '2026-11-02 08:50:00', source: AppointmentSource::PORTAL);
        $this->appointment(self::U3, self::OWNER, null, '2026-11-05 10:00:00');
        $this->appointment(self::P1, self::OWNER, self::PET, '2026-11-05 12:00:00', AppointmentStatus::CANCELLED, cancelledBy: CancellationActor::CLIENT);
        $this->appointment(self::P2, self::OWNER, self::PET, '2026-10-30 09:00:00', AppointmentStatus::COMPLETED);
        $this->appointment(self::P3, self::OWNER, self::PET, '2026-11-01 09:00:00');
        $this->appointment(self::P4, self::OWNER, self::PET, '2026-10-25 09:00:00', AppointmentStatus::DECLINED);
        // Neither the clients' nor their animals', and another clinic.
        $this->appointment('01960000-0000-7000-8000-000000000901', self::OTHER_OWNER, self::STRANGER, '2026-11-04 09:00:00');
        $this->appointment('01960000-0000-7000-8000-000000000902', self::OWNER, self::PET, '2026-11-04 09:00:00', clinicId: self::OTHER_CLINIC);
    }

    /**
     * @param list<string> $owners
     * @param list<string> $animals
     *
     * @return CursorPage<PortalAppointmentItem>
     */
    private function list(AppointmentListScope $scope, ?string $cursor = null, ?int $limit = null, array $owners = [self::OWNER], array $animals = [self::PET, self::PET2]): CursorPage
    {
        $handler = self::getContainer()->get(ListAppointmentsForOwnersHandler::class);
        \assert($handler instanceof ListAppointmentsForOwnersHandler);

        return $handler(new ListAppointmentsForOwners(self::CLINIC, $owners, $animals, $scope, $cursor, $limit, new \DateTimeImmutable(self::NOW, new \DateTimeZone('UTC'))));
    }

    private function get(string $id, string $clinicId = self::CLINIC): ?PortalAppointmentItem
    {
        $container  = self::getContainer();
        $connection = $container->get(Connection::class);
        $settings   = $container->get(OnlineBookingSettingsRepositoryInterface::class);
        \assert($connection instanceof Connection && $settings instanceof OnlineBookingSettingsRepositoryInterface);
        $clock = new FrozenClock(new \DateTimeImmutable(self::NOW, new \DateTimeZone('UTC')));

        return new GetOwnerAppointmentHandler($connection, $settings, $clock)(new GetOwnerAppointment($clinicId, $id));
    }

    /**
     * @param list<string> $animalIds
     *
     * @return list<CareBookingItem>
     */
    private function careBookings(array $animalIds, string $fromUtc): array
    {
        $handler = self::getContainer()->get(ListCareBookingsForAnimalsHandler::class);
        \assert($handler instanceof ListCareBookingsForAnimalsHandler);

        return $handler(new ListCareBookingsForAnimals(self::CLINIC, $animalIds, new \DateTimeImmutable($fromUtc, new \DateTimeZone('UTC'))));
    }

    /**
     * @param CursorPage<PortalAppointmentItem> $page
     *
     * @return list<string>
     */
    private static function ids(CursorPage $page): array
    {
        return array_map(static fn (PortalAppointmentItem $i): string => $i->id, $page->items);
    }

    private function motif(string $id, string $label, ?BookingPreventiveCareKind $kind): void
    {
        $motifs = self::getContainer()->get(BookingMotifRepositoryInterface::class);
        \assert($motifs instanceof BookingMotifRepositoryInterface);
        $motifs->save(BookingMotif::create(
            BookingMotifId::fromString($id),
            ClinicId::fromString(self::CLINIC),
            $label,
            null,
            30,
            true,
            ConfirmationMode::AUTO,
            [PlanningBlockType::CONSULTATION],
            [UserId::fromString(self::VET)],
            $kind,
            false,
            0,
        ));
    }

    private function appointment(
        string $id,
        ?string $ownerId,
        ?string $animalId,
        string $startsAtUtc,
        AppointmentStatus $status = AppointmentStatus::PLANNED,
        ?string $motifId = null,
        string $createdAt = '2026-10-01 08:00:00',
        AppointmentSource $source = AppointmentSource::CLINIC,
        ?CancellationActor $cancelledBy = null,
        string $clinicId = self::CLINIC,
    ): void {
        $utc = new \DateTimeZone('UTC');

        AppointmentEntityFactory::new()
            ->withId($id)
            ->withClinicId($clinicId)
            ->withPractitionerUserId(self::VET)
            ->withOwnerAndAnimal($ownerId, $animalId)
            ->startingAt(new \DateTimeImmutable($startsAtUtc, $utc), 30)
            ->withStatus($status)
            ->afterInstantiate(static function (AppointmentEntity $entity) use ($motifId, $createdAt, $source, $cancelledBy, $utc): void {
                $entity->setBookingMotifId(null === $motifId ? null : Uuid::fromString($motifId));
                $entity->setCreatedAt(new \DateTimeImmutable($createdAt, $utc));
                $entity->setSource($source);
                $entity->setCancelledBy($cancelledBy);
                $entity->setReason('Motif interne confidentiel');
                $entity->setNotes('Peut mordre');
            })
            ->create()
        ;
    }
}
