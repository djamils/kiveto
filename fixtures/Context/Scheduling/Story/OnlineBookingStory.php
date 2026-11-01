<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Scheduling\Story;

use App\Context\Clinic\Application\Command\Staff\OnboardStaffMember\OnboardStaffMember;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalTitle;
use App\Context\Scheduling\Application\Command\ArchiveBookingMotif\ArchiveBookingMotif;
use App\Context\Scheduling\Application\Command\CancelAppointment\CancelAppointment;
use App\Context\Scheduling\Application\Command\ConfirmAppointment\ConfirmAppointment;
use App\Context\Scheduling\Application\Command\DeclineAppointment\DeclineAppointment;
use App\Context\Scheduling\Application\Command\SaveBookingMotif\SaveBookingMotif;
use App\Context\Scheduling\Application\Command\ScanExpiredPendingAppointments\ScanExpiredPendingAppointments;
use App\Context\Scheduling\Application\Command\UpdateOnlineBookingSettings\UpdateOnlineBookingSettings;
use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentSource;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PractitionerAssignee;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\System\IdentityAccess\Factory\ClinicUserFactory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Zenstruck\Foundry\Story;

/**
 * Online booking (slice 4a) in every state the settings page, the agenda,
 * the dashboard and the bell show:
 *
 * | Clinic   | Online booking | What it holds                                                          |
 * |----------|----------------|------------------------------------------------------------------------|
 * | Lyon     | enabled        | 4 motifs (manual for known clients, vaccination, open to new clients,  |
 * |          |                | archived); 6 pending requests (one expiring within the hour), one      |
 * |          |                | declined, one auto-declined (`expired`), one withdrawn by the client,  |
 * |          |                | one confirmed — all for Lemoine, on the coming weekdays                |
 * | Paris    | disabled       | one motif configured, no pending request                               |
 * | Vercors  | enabled        | an empty opening week: every day of the Portal calendar is closed      |
 *
 * The requests are made through `Appointment::request()` and published, so
 * that the notification state and the « RDV en ligne à confirmer » bell
 * entries exist (queued, drained by NotificationDemoStory). The clients are
 * Lyon clients without a Portal link, so that the Portal accounts of slices
 * 2–3 keep their own next appointments.
 */
final class OnlineBookingStory extends Story
{
    // A clinic without any opening hour: its Portal calendar is closed every day.
    public const string EMPTY_WEEK_CLINIC_ID = '01960000-0000-7000-8000-000000000103';

    private const string LYON  = ClinicDataStory::GROUP_CLINIC_ID;
    private const string PARIS = ClinicDataStory::INDEPENDENT_CLINIC_ID;

    /** Clients the Portal and notification demos rely on: left alone. */
    private const array RESERVED_LAST_NAMES = ['Garnier', 'Marchand', 'Rey', 'Girard', 'Bonnet'];

    private \DateTimeZone $tz;

    private \DateTimeImmutable $now;

    /** @var list<array{animalId: string, clientId: string}> */
    private array $patients = [];

    public function __construct(
        private readonly ClockInterface $clock,
        private readonly CommandBusInterface $commandBus,
        private readonly Connection $connection,
        private readonly AppointmentRepositoryInterface $appointments,
        private readonly DomainEventPublisher $domainEventPublisher,
        private readonly UuidGeneratorInterface $uuidGenerator,
    ) {
        $this->tz = new \DateTimeZone('Europe/Paris');
    }

    public function build(): void
    {
        $this->now = $this->clock->now()->setTimezone($this->tz);

        $rousseau = $this->user('vet@kiveto.local');
        $lemoine  = $this->user('vet.lyon@kiveto.local');
        $bernard  = $this->user('vet2@kiveto.local');

        $controlMotif = $this->lyon($rousseau, $lemoine);
        $this->paris($rousseau, $bernard);
        $this->emptyWeekClinic($rousseau);

        $this->requests($lemoine, $controlMotif);
    }

    /**
     * Lyon takes bookings online; returns the manual motif its requests use.
     */
    private function lyon(string $rousseau, string $lemoine): string
    {
        $this->commandBus->dispatch(new UpdateOnlineBookingSettings(
            clinicId: self::LYON,
            enabled: true,
            gridStepMinutes: 15,
            minLeadTimeMinutes: 120,
            minLeadTimeManualHours: 24,
            horizonDays: 60,
            cancellationWindowHours: 24,
            newClientBookingEnabled: true,
        ));

        // Open to new clients (who always wait for the clinic).
        $this->motif(self::LYON, 'Consultation', 'Un problème de santé, un avis, un contrôle de routine.', 30, 'auto', ['consultation'], [$rousseau, $lemoine], null, true, 0);

        // Known clients wait for the clinic too: the pending requests below.
        $control = $this->motif(self::LYON, 'Contrôle / suivi', 'Suivi d\'un traitement ou contrôle post-opératoire.', 20, 'manual', ['consultation', 'health_check'], [$lemoine], null, false, 1);

        $this->motif(self::LYON, 'Vaccination', 'Primo-vaccination ou rappel annuel.', 20, 'auto', ['consultation', 'health_check'], [$rousseau, $lemoine], 'vaccination', false, 2);

        // Archived: hidden from the Portal, kept by the bookings that used it.
        $scaling = $this->motif(self::LYON, 'Détartrage', null, 45, 'auto', ['surgery'], [$lemoine], null, false, 3);
        $this->commandBus->dispatch(new ArchiveBookingMotif(self::LYON, $scaling));

        return $control;
    }

    /**
     * Paris has configured a motif but keeps online booking off.
     */
    private function paris(string $rousseau, string $bernard): void
    {
        $this->commandBus->dispatch(new UpdateOnlineBookingSettings(
            clinicId: self::PARIS,
            enabled: false,
            gridStepMinutes: 20,
            minLeadTimeMinutes: 240,
            minLeadTimeManualHours: 48,
            horizonDays: 30,
            cancellationWindowHours: 48,
            newClientBookingEnabled: false,
        ));

        $this->motif(self::PARIS, 'Consultation', null, 30, 'auto', ['consultation'], [$rousseau, $bernard], null, false, 0);
    }

    /**
     * A clinic of its own whose opening week is empty; booking is on, so the
     * Portal reaches its calendar and finds every day closed.
     */
    private function emptyWeekClinic(string $rousseau): void
    {
        ClinicEntityFactory::new()
            ->withId(self::EMPTY_WEEK_CLINIC_ID)
            ->withSlug('clinic-vercors')
            ->withName('Clinique du Vercors')
            ->withOpeningHours(['week' => [], 'closures' => []])
            ->create(['timeZone' => 'Europe/Paris', 'locale' => 'fr-FR'])
        ;

        $this->commandBus->dispatch(new OnboardStaffMember(
            clinicId: self::EMPTY_WEEK_CLINIC_ID,
            userId: $rousseau,
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            firstName: 'Éric',
            lastName: 'Rousseau',
            displayName: 'Rousseau',
            agendaColor: '#4f86c6',
            sortOrder: 0,
            isVisibleInAgenda: true,
            registrationNumber: 'FR-12345',
            professionalTitle: ProfessionalTitle::DR,
            isClinicAdministrator: true,
        ));

        $this->commandBus->dispatch(new UpdateOnlineBookingSettings(
            clinicId: self::EMPTY_WEEK_CLINIC_ID,
            enabled: true,
            gridStepMinutes: 15,
            minLeadTimeMinutes: 120,
            minLeadTimeManualHours: 24,
            horizonDays: 60,
            cancellationWindowHours: 24,
            newClientBookingEnabled: false,
        ));

        $this->motif(self::EMPTY_WEEK_CLINIC_ID, 'Consultation', null, 30, 'auto', ['consultation'], [$rousseau], null, false, 0);
    }

    /**
     * Lemoine's online requests, on her weekday afternoons at Lyon (13:00–19:00).
     */
    private function requests(string $lemoine, string $motifId): void
    {
        // Pending: [weekday, time, requested ago] — the first one, requested
        // 47 h 10 ago, expires within the hour.
        foreach ([[1, '13:30', '-47 hours -10 minutes'], [1, '14:30', '-3 hours'], [2, '13:00', '-20 hours'], [2, '15:40', '-1 hour'], [3, '17:00', '-30 minutes'], [4, '13:20', '-5 hours']] as [$day, $time, $ago]) {
            $this->request($lemoine, $motifId, $this->weekday($day, $time), $this->now->modify($ago));
        }

        // Confirmed by the clinic: planned, the client gets the confirmation.
        $confirmed = $this->request($lemoine, $motifId, $this->weekday(2, '14:00'), $this->now->modify('-22 hours'));
        $this->commandBus->dispatch(new ConfirmAppointment($confirmed, self::LYON));

        // Declined by the clinic: the slot is free again.
        $declined = $this->request($lemoine, $motifId, $this->weekday(3, '14:00'), $this->now->modify('-26 hours'));
        $this->commandBus->dispatch(new DeclineAppointment($declined, self::LYON));

        // Withdrawn by the client before the clinic answered.
        $withdrawn = $this->request($lemoine, $motifId, $this->weekday(3, '15:00'), $this->now->modify('-10 hours'));
        $this->commandBus->dispatch(new CancelAppointment(
            appointmentId: $withdrawn,
            clinicId: self::LYON,
            cancelledBy: 'client',
            cancellationReason: 'no_longer_needed',
        ));

        // Not answered in time: requested 50 h ago, expired 2 h ago, declined
        // as `expired` by the 15-minute scan (its decline is queued on `async`).
        $this->request($lemoine, $motifId, $this->weekday(2, '16:30'), $this->now->modify('-50 hours'));
        $this->commandBus->dispatch(new ScanExpiredPendingAppointments());
    }

    /**
     * A request as the Portal makes it: pending, expiring as
     * OnlineBookingSettings::pendingExpiry() computes it (48 h after the
     * request, or 2 h before the start when sooner), then published.
     */
    private function request(string $practitionerId, string $motifId, \DateTimeImmutable $start, \DateTimeImmutable $requestedAt): string
    {
        $patient = $this->nextPatient();
        $id      = $this->uuidGenerator->generate();
        // Instants are stored as UTC wall-clock times, like the handlers' clock gives them
        $utc         = new \DateTimeZone('UTC');
        $requestedAt = $requestedAt->setTimezone($utc);
        $start       = $start->setTimezone($utc);

        $appointment = Appointment::request(
            id: AppointmentId::fromString($id),
            clinicId: ClinicId::fromString(self::LYON),
            practitionerAssignee: new PractitionerAssignee(UserId::fromString($practitionerId)),
            timeSlot: new TimeSlot($start, 20),
            reason: 'Contrôle / suivi',
            notes: null,
            createdAt: $requestedAt,
            ownerId: $patient['clientId'],
            animalId: $patient['animalId'],
            bookingMotifId: $motifId,
            source: AppointmentSource::PORTAL,
            pendingExpiresAt: min($requestedAt->modify('+48 hours'), $start->modify('-2 hours')),
        );

        $this->connection->transactional(function () use ($appointment): void {
            $this->appointments->save($appointment);
            $this->domainEventPublisher->publish($appointment);
        });

        return $id;
    }

    /**
     * @param list<string> $blockTypes
     * @param list<string> $practitionerIds
     */
    private function motif(
        string $clinicId,
        string $label,
        ?string $description,
        int $durationMin,
        string $confirmation,
        array $blockTypes,
        array $practitionerIds,
        ?string $preventiveCareKind,
        bool $newClientsAllowed,
        int $sortOrder,
    ): string {
        $id = $this->commandBus->dispatch(new SaveBookingMotif(
            clinicId: $clinicId,
            motifId: null,
            label: $label,
            description: $description,
            durationMin: $durationMin,
            onlineEnabled: true,
            confirmationKnownClient: $confirmation,
            allowedBlockTypes: $blockTypes,
            practitionerIds: $practitionerIds,
            preventiveCareKind: $preventiveCareKind,
            newClientsAllowed: $newClientsAllowed,
            sortOrder: $sortOrder,
        ));
        \assert(\is_string($id));

        return $id;
    }

    /**
     * The n-th weekday (Monday to Friday) after today, at the given local time.
     */
    private function weekday(int $n, string $time): \DateTimeImmutable
    {
        $date = $this->now->setTime(0, 0);

        while ($n > 0) {
            $date = $date->modify('+1 day');

            if ((int) $date->format('N') <= 5) {
                --$n;
            }
        }

        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $date->setTime($hour, $minute);
    }

    /**
     * Lyon's patients without a Portal link, in turn.
     *
     * @return array{animalId: string, clientId: string}
     */
    private function nextPatient(): array
    {
        if ([] === $this->patients) {
            $rows = $this->connection->fetchAllAssociative(
                "SELECT BIN_TO_UUID(a.id) AS animal_id, BIN_TO_UUID(c.id) AS client_id
                 FROM animal__animals a
                 JOIN animal__ownerships o ON o.animal_id = a.id AND o.status = 'active' AND o.role = 'primary'
                 JOIN client__clients c ON c.id = o.client_id
                 WHERE a.clinic_id = UUID_TO_BIN(:clinicId) AND a.status = 'ACTIVE'
                   AND c.last_name NOT IN (:reserved)
                   AND c.id NOT IN (SELECT l.client_id FROM client_portal__client_links l)
                 ORDER BY c.last_name, a.name",
                ['clinicId' => self::LYON, 'reserved' => self::RESERVED_LAST_NAMES],
                ['reserved' => ArrayParameterType::STRING],
            );

            foreach ($rows as $row) {
                $this->patients[] = [
                    'animalId' => RowAccessor::string($row, 'animal_id'),
                    'clientId' => RowAccessor::string($row, 'client_id'),
                ];
            }

            if ([] === $this->patients) {
                throw new \RuntimeException('No Lyon patient without a Portal link: the animal stories must be loaded first.');
            }
        }

        $patient          = array_shift($this->patients);
        $this->patients[] = $patient;

        return $patient;
    }

    private function user(string $email): string
    {
        $user = ClinicUserFactory::repository()->findOneBy(['email' => $email]);

        if (null === $user) {
            throw new \RuntimeException(\sprintf('%s not found. The staff stories must be loaded first.', $email));
        }

        return $user->getId()->toRfc4122();
    }
}
