<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Support;

use App\Context\Scheduling\Application\Command\BookOnlineAppointment\BookOnlineAppointmentHandler;
use App\Context\Scheduling\Application\Command\CancelOnlineAppointment\CancelOnlineAppointmentHandler;
use App\Context\Scheduling\Application\Command\RescheduleOnlineAppointment\RescheduleOnlineAppointmentHandler;
use App\Context\Scheduling\Application\Port\ClinicOpeningHours;
use App\Context\Scheduling\Application\Port\PlanningBlockOccurrence;
use App\Context\Scheduling\Application\Port\PractitionerDayLockInterface;
use App\Context\Scheduling\Application\Service\OnlineChangeNotice;
use App\Context\Scheduling\Application\Service\OnlineSlotChecker;
use App\Context\Scheduling\Application\Service\PendingAppointmentLoader;
use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Booking\BookingMotif;
use App\Context\Scheduling\Domain\Booking\BookingMotifId;
use App\Context\Scheduling\Domain\Booking\ConfirmationMode;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettings;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentSource;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\CancellationActor;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\PractitionerAssignee;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;

/**
 * Online booking at Clinique Montmartre (Europe/Paris), on in-memory
 * doubles. Now is Monday 13 April 2026, 08:00 in Paris. Dr Rousseau and Dr
 * Lambert hold a consultation block 09:00–12:00 every weekday; the clinic
 * opens 08:30–12:30. Settings: grid 15 min, lead time 2 h, manual lead
 * time 26 h, cancellation window 24 h.
 */
final class OnlineBookingWorld
{
    public const string CLINIC       = Appointments::CLINIC;
    public const string OTHER_CLINIC = '01960000-0000-7000-8000-0000000c0002';
    public const string ROUSSEAU     = Appointments::ROUSSEAU;
    public const string LAMBERT      = Appointments::LAMBERT;
    public const string MARTIN       = '01960000-0000-7000-8000-0000000d0003';
    public const string OWNER        = Appointments::OWNER;
    public const string LEFEVRE      = Appointments::LEFEVRE;
    public const string ANIMAL       = Appointments::ANIMAL;
    public const string NEW_PET      = '01960000-0000-7000-8000-0000000f0002';
    public const string AUTO         = '01960000-0000-7000-8000-00000000b001';
    public const string MANUAL       = '01960000-0000-7000-8000-00000000b002';
    public const string OFFLINE      = '01960000-0000-7000-8000-00000000b003';
    public const string ARCHIVED     = '01960000-0000-7000-8000-00000000b004';
    public const string BOOKED       = '01960000-0000-7000-8000-0000000a0101';
    public const string NOW          = '2026-04-13 06:00';

    public InMemoryAppointments $appointments;
    public InMemoryBookingMotifs $motifs;
    public InMemoryOnlineBookingSettings $settings;
    public FakeAvailabilitySources $sources;
    public PractitionerDayLockInterface $dayLock;
    public RecordingDayLock $recordingLock;
    public MovableClock $clock;
    public CapturingEventBus $events;
    public CapturingPractitionerBell $bell;
    public FakeClientContacts $contacts;
    public FakeAnimals $animals;

    public function __construct()
    {
        $this->appointments  = new InMemoryAppointments();
        $this->motifs        = new InMemoryBookingMotifs();
        $this->settings      = new InMemoryOnlineBookingSettings();
        $this->sources       = new FakeAvailabilitySources();
        $this->recordingLock = new RecordingDayLock();
        $this->dayLock       = $this->recordingLock;
        $this->clock         = new MovableClock(self::utc(self::NOW));
        $this->events        = new CapturingEventBus();
        $this->bell          = new CapturingPractitionerBell();
        $this->contacts      = new FakeClientContacts();
        $this->animals       = new FakeAnimals();

        $this->motifs->save(self::motif(self::AUTO, 'Consultation', ConfirmationMode::AUTO));
        $this->motifs->save(self::motif(self::MANUAL, 'Vaccination', ConfirmationMode::MANUAL));
        $this->motifs->save(self::motif(self::OFFLINE, 'Chirurgie', ConfirmationMode::AUTO, online: false));
        $archived = self::motif(self::ARCHIVED, 'Ancien motif', ConfirmationMode::AUTO);
        $archived->archive(self::utc('2026-04-01 10:00'));
        $this->motifs->save($archived);

        $this->settings->save(self::settings(enabled: true));

        $weekday                 = [['opensAt' => '08:30', 'closesAt' => '12:30']];
        $this->sources->clinic   = new ClinicOpeningHours(true, new \DateTimeZone('Europe/Paris'), [1 => $weekday, 2 => $weekday, 3 => $weekday, 4 => $weekday, 5 => $weekday], []);
        $this->sources->eligible = [self::ROUSSEAU, self::LAMBERT, self::MARTIN];

        foreach (['2026-04-13', '2026-04-14', '2026-04-15', '2026-04-16', '2026-04-17'] as $date) {
            $this->sources->occurrences[] = new PlanningBlockOccurrence($date, '09:00', '12:00', PlanningBlockType::CONSULTATION, 0);
        }
    }

    public static function settings(bool $enabled, int $cancellationWindowHours = 24): OnlineBookingSettings
    {
        return new OnlineBookingSettings(
            clinicId: ClinicId::fromString(self::CLINIC),
            enabled: $enabled,
            gridStepMinutes: 15,
            minLeadTimeMinutes: 120,
            minLeadTimeManualHours: 26,
            horizonDays: 60,
            cancellationWindowHours: $cancellationWindowHours,
            newClientBookingEnabled: true,
        );
    }

    /**
     * A booking already in the agenda, created on $createdAt.
     */
    public function existing(
        string $startsAtUtc,
        AppointmentStatus $status = AppointmentStatus::PLANNED,
        AppointmentSource $source = AppointmentSource::PORTAL,
        ?string $motifId = self::AUTO,
        string $createdAt = '2026-04-10 08:00',
        string $id = self::BOOKED,
        string $practitioner = self::ROUSSEAU,
        ?string $ownerId = self::OWNER,
        ?\DateTimeImmutable $pendingExpiresAt = null,
        ?string $createdPetId = null,
        ?CancellationActor $cancelledBy = null,
    ): Appointment {
        $appointment = Appointment::reconstitute(
            id: AppointmentId::fromString($id),
            clinicId: ClinicId::fromString(self::CLINIC),
            practitionerAssignee: new PractitionerAssignee(UserId::fromString($practitioner)),
            timeSlot: new TimeSlot(self::utc($startsAtUtc), 30),
            status: $status,
            reason: 'Consultation',
            notes: null,
            createdAt: self::utc($createdAt),
            serviceStartedAt: null,
            ownerId: $ownerId,
            animalId: self::ANIMAL,
            source: $source,
            bookingMotifId: $motifId,
            pendingExpiresAt: $pendingExpiresAt ?? ($status->isPending() ? self::utc('2026-04-14 06:00') : null),
            cancelledBy: $cancelledBy,
            createdPetId: $createdPetId,
        );
        $this->appointments->save($appointment);

        return $appointment;
    }

    public function book(string ...$ids): BookOnlineAppointmentHandler
    {
        $ids = [] === $ids ? ['01960000-0000-7000-8000-0000000a0201'] : array_values($ids);

        return new BookOnlineAppointmentHandler(
            appointments: $this->appointments,
            motifs: $this->motifs,
            settings: $this->settings,
            slots: $this->slots(),
            timezoneResolver: new ParisTimezone(),
            dayLock: $this->dayLock,
            uuidGenerator: new class($ids) implements UuidGeneratorInterface {
                /** @param list<string> $ids */
                public function __construct(private array $ids)
                {
                }

                public function generate(): string
                {
                    return array_shift($this->ids) ?? throw new \LogicException('No id left.');
                }
            },
            clock: $this->clock,
            domainEventPublisher: new DomainEventPublisher($this->events),
        );
    }

    public function reschedule(): RescheduleOnlineAppointmentHandler
    {
        return new RescheduleOnlineAppointmentHandler(
            appointments: $this->appointments,
            settings: $this->settings,
            slots: $this->slots(),
            timezoneResolver: new ParisTimezone(),
            dayLock: $this->dayLock,
            clock: $this->clock,
            domainEventPublisher: new DomainEventPublisher($this->events),
            notice: $this->notice(),
        );
    }

    public function cancel(): CancelOnlineAppointmentHandler
    {
        return new CancelOnlineAppointmentHandler(
            loader: new PendingAppointmentLoader($this->appointments, new ParisTimezone(), $this->dayLock),
            appointments: $this->appointments,
            settings: $this->settings,
            clock: $this->clock,
            domainEventPublisher: new DomainEventPublisher($this->events),
            notice: $this->notice(),
        );
    }

    /**
     * @return list<class-string>
     */
    public function eventClasses(): array
    {
        return array_map(static fn (object $event): string => $event::class, $this->events->events);
    }

    public function stored(string $id): Appointment
    {
        return $this->appointments->findById(AppointmentId::fromString($id)) ?? throw new \LogicException('Unknown appointment.');
    }

    public static function utc(string $at): \DateTimeImmutable
    {
        return new \DateTimeImmutable($at, new \DateTimeZone('UTC'));
    }

    public static function paris(string $at): \DateTimeImmutable
    {
        return new \DateTimeImmutable($at, new \DateTimeZone('Europe/Paris'));
    }

    private function slots(): OnlineSlotChecker
    {
        return new OnlineSlotChecker($this->motifs, $this->settings, $this->sources, $this->sources, $this->sources, $this->sources, $this->clock);
    }

    private function notice(): OnlineChangeNotice
    {
        return new OnlineChangeNotice(new ParisTimezone(), $this->contacts, $this->animals, $this->bell);
    }

    private static function motif(string $id, string $label, ConfirmationMode $confirmation, bool $online = true): BookingMotif
    {
        return BookingMotif::create(
            BookingMotifId::fromString($id),
            ClinicId::fromString(self::CLINIC),
            $label,
            null,
            30,
            $online,
            $confirmation,
            [PlanningBlockType::CONSULTATION],
            [UserId::fromString(self::ROUSSEAU), UserId::fromString(self::LAMBERT)],
            null,
            true,
            0,
        );
    }
}
