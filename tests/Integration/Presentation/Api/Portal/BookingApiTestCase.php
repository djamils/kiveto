<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalTitle;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Embeddable\LetterheadEmbeddable;
use App\Context\Scheduling\Domain\Booking\BookingMotif;
use App\Context\Scheduling\Domain\Booking\BookingMotifId;
use App\Context\Scheduling\Domain\Booking\BookingMotifRepositoryInterface;
use App\Context\Scheduling\Domain\Booking\BookingPreventiveCareKind;
use App\Context\Scheduling\Domain\Booking\ConfirmationMode;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettings;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettingsRepositoryInterface;
use App\Context\Scheduling\Domain\Notification\AppointmentNotificationState;
use App\Context\Scheduling\Domain\Notification\AppointmentNotificationStateRepositoryInterface;
use App\Context\Scheduling\Domain\PlanningBlock;
use App\Context\Scheduling\Domain\Repository\PlanningBlockRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentSource;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\CancellationActor;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\RecurrenceRule;
use App\Context\Scheduling\Domain\ValueObject\TimeRange;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity\AppointmentEntity;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\Context\Clinic\Factory\StaffProfileEntityFactory;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * Slice 4 setup on top of slice 3's: the linked clinic (Europe/Paris, at
 * 18 rue Lepic) opens Monday to Friday 08:30–12:30 and 14:00–19:00, its
 * online booking is enabled (grid 15 min, lead time 2 h, horizon 60 days,
 * cancellation window 24 h). Today is Monday 2 November 2026, 11:00 in Paris.
 */
abstract class BookingApiTestCase extends PetsApiTestCase
{
    protected function clinicAttributes(): array
    {
        $weekday = [['opensAt' => '08:30', 'closesAt' => '12:30'], ['opensAt' => '14:00', 'closesAt' => '19:00']];

        return [
            'openingHours' => ['week' => [1 => $weekday, 2 => $weekday, 3 => $weekday, 4 => $weekday, 5 => $weekday], 'closures' => [['date' => '2026-11-11', 'reason' => 'Armistice']]],
            'letterhead'   => new LetterheadEmbeddable(streetLine1: '18 rue Lepic', postalCode: '75018', city: 'Paris', countryCode: 'FR'),
        ];
    }

    protected function enableOnlineBooking(bool $enabled = true, int $horizonDays = 60, string $clinicId = self::CLINIC): void
    {
        $this->service(OnlineBookingSettingsRepositoryInterface::class)->save(
            new OnlineBookingSettings(ClinicId::fromString($clinicId), $enabled, 15, 120, 24, $horizonDays, 24, false),
        );
    }

    /**
     * An online-bookable motif of the clinic.
     *
     * @param list<string> $practitionerIds
     */
    protected function motif(
        string $label,
        array $practitionerIds,
        ?BookingPreventiveCareKind $kind = null,
        bool $online = true,
        bool $archived = false,
        int $sortOrder = 0,
        string $clinicId = self::CLINIC,
        ConfirmationMode $confirmation = ConfirmationMode::AUTO,
    ): string {
        $id    = Uuid::v7()->toRfc4122();
        $motif = BookingMotif::create(
            BookingMotifId::fromString($id),
            ClinicId::fromString($clinicId),
            $label,
            'Description de ' . $label,
            30,
            $online,
            $confirmation,
            [PlanningBlockType::CONSULTATION],
            array_map(UserId::fromString(...), $practitionerIds),
            $kind,
            false,
            $sortOrder,
        );

        if ($archived) {
            $motif->archive(new \DateTimeImmutable('2026-10-01'));
        }

        $this->service(BookingMotifRepositoryInterface::class)->save($motif);

        return $id;
    }

    /** A veterinarian of the clinic, printed « Dr <first> <last> »; returns their user id. */
    protected function veterinarian(string $firstName, string $lastName, string $clinicId = self::CLINIC): string
    {
        $userId     = ClinicUserFactory::createOne()->getId()->toRfc4122();
        $membership = Uuid::v7()->toRfc4122();
        ClinicMembershipEntityFactory::new()->withId($membership)->withClinicId($clinicId)->withUserId($userId)->withRole(ClinicMemberRole::VETERINARY)->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;
        StaffProfileEntityFactory::new()->forMembership(Uuid::fromString($membership))->create([
            'firstName'         => $firstName,
            'lastName'          => $lastName,
            'displayName'       => 'Dr ' . $firstName . ' ' . $lastName,
            'professionalTitle' => ProfessionalTitle::DR,
        ]);

        return $userId;
    }

    protected function block(string $practitionerId, PlanningBlockType $type, string $date, string $start, string $end, ?RecurrenceRule $rule = null): void
    {
        $this->service(PlanningBlockRepositoryInterface::class)->save(PlanningBlock::create(
            id: PlanningBlockId::fromString(Uuid::v7()->toRfc4122()),
            clinicId: ClinicId::fromString(self::CLINIC),
            staffUserId: UserId::fromString($practitionerId),
            type: $type,
            timeRange: new TimeRange($date, $start, $end),
            capacityPerHour: $type->acceptsAppointments() ? 4 : 0,
            rule: $rule ?? RecurrenceRule::none(),
            note: null,
        ));
    }

    /** An appointment of the clinic, its reason and notes set to what must never leak. */
    protected function appointment(
        ?string $petId,
        string $startsAtUtc,
        AppointmentStatus $status = AppointmentStatus::PLANNED,
        ?string $motifId = null,
        ?string $practitionerId = null,
        string $clientId = self::CLIENT,
        AppointmentSource $source = AppointmentSource::CLINIC,
        ?CancellationActor $cancelledBy = null,
        string $createdAtUtc = '2026-10-01 08:00:00',
    ): string {
        $id  = Uuid::v7()->toRfc4122();
        $utc = new \DateTimeZone('UTC');

        $factory = AppointmentEntityFactory::new()
            ->withId($id)
            ->withClinicId(self::CLINIC)
            ->withOwnerAndAnimal($clientId, $petId)
            ->startingAt(new \DateTimeImmutable($startsAtUtc, $utc), 30)
            ->withStatus($status)
            ->afterInstantiate(static function (AppointmentEntity $entity) use ($motifId, $source, $cancelledBy, $createdAtUtc, $utc): void {
                $entity->setBookingMotifId(null === $motifId ? null : Uuid::fromString($motifId));
                $entity->setSource($source);
                $entity->setCancelledBy($cancelledBy);
                $entity->setCreatedAt(new \DateTimeImmutable($createdAtUtc, $utc));
                $entity->setReason('Motif interne confidentiel');
                $entity->setNotes('Peut mordre');
            })
        ;

        (null === $practitionerId ? $factory : $factory->withPractitionerUserId($practitionerId))->create();

        return $id;
    }

    /** The appointment's notification state after `$messages` client messages. */
    protected function notified(string $appointmentId, int $messages): void
    {
        $this->service(AppointmentNotificationStateRepositoryInterface::class)->save(AppointmentNotificationState::reconstitute(
            AppointmentId::fromString($appointmentId),
            ClinicId::fromString(self::CLINIC),
            new \DateTimeImmutable('2026-10-01'),
            null,
            false,
            null,
            $messages,
            null,
            null,
        ));
    }

    /**
     * A POST of the signed-in owner (or of `$token`'s), with an
     * Idempotency-Key when given.
     *
     * @param array<string, mixed> $body
     */
    protected function authPost(string $path, array $body, ?string $idempotencyKey = null, ?string $token = null): Response
    {
        $server = ['HTTP_AUTHORIZATION' => 'Bearer ' . ($token ?? $this->token)];

        if (null !== $idempotencyKey) {
            $server['HTTP_IDEMPOTENCY_KEY'] = $idempotencyKey;
        }

        return $this->post($path, $body, $server);
    }

    protected static function clinicPath(string $path, string $clinicId = self::CLINIC): string
    {
        return '/clinics/' . $clinicId . $path;
    }
}
