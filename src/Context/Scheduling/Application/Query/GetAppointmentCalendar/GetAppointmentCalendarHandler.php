<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\GetAppointmentCalendar;

use App\Context\Scheduling\Application\Port\AnimalDirectoryInterface;
use App\Context\Scheduling\Application\Port\CalendarEvent;
use App\Context\Scheduling\Application\Port\CalendarEventWriterInterface;
use App\Context\Scheduling\Application\Port\ClinicDirectoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * The event carries the uid of the e-mailed invitation (AppointmentMessageComposer)
 * and the sequence of the last one sent: e-mail number n carries sequence
 * n − 1, the notification counter being the number of the last message.
 * Read without lock: a sync running meanwhile only makes the sequence one
 * behind, which a later download catches up.
 */
#[AsMessageHandler]
final readonly class GetAppointmentCalendarHandler
{
    /** Kept equal to AppointmentMessageComposer's invitation uid. */
    public const string UID_FORMAT = '%s@kiveto.app';

    private const string UNKNOWN_ANIMAL = 'votre animal';

    public function __construct(
        private Connection $connection,
        private ClinicDirectoryInterface $clinics,
        private AnimalDirectoryInterface $animals,
        private CalendarEventWriterInterface $writer,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(GetAppointmentCalendar $query): ?string
    {
        if (!Uuid::isValid($query->appointmentId)) {
            return null;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT a.status, a.starts_at_utc, a.duration_minutes, BIN_TO_UUID(a.animal_id) AS animal_id,'
            . ' BIN_TO_UUID(a.practitioner_user_id) AS practitioner_user_id, m.label AS motif_label, n.notification_counter'
            . ' FROM scheduling__appointments a'
            . ' LEFT JOIN scheduling__booking_motifs m ON m.id = a.booking_motif_id AND m.clinic_id = a.clinic_id'
            . ' LEFT JOIN scheduling__appointment_notifications n ON n.appointment_id = a.id'
            . ' WHERE a.id = :id AND a.clinic_id = UUID_TO_BIN(:clinicId)',
            ['id' => Uuid::fromString($query->appointmentId)->toBinary(), 'clinicId' => $query->clinicId],
            ['id' => ParameterType::BINARY],
        );

        if (false === $row) {
            return null;
        }

        $status = AppointmentStatus::from(RowAccessor::string($row, 'status'));

        if (!$status->occupiesSlot()) {
            return null;
        }

        $clinic       = $this->clinics->clinic($query->clinicId);
        $animalId     = RowAccessor::nullableString($row, 'animal_id');
        $animal       = (null !== $animalId ? $this->animals->nameOf($query->clinicId, $animalId) : null) ?? self::UNKNOWN_ANIMAL;
        $motif        = RowAccessor::nullableString($row, 'motif_label');
        $practitioner = $this->clinics->printedName($query->clinicId, RowAccessor::string($row, 'practitioner_user_id'));
        $description  = array_filter([
            null !== $motif ? 'Motif : ' . $motif : null,
            null !== $practitioner ? 'Vétérinaire : ' . $practitioner : null,
        ]);

        return $this->writer->publish(new CalendarEvent(
            uid: \sprintf(self::UID_FORMAT, Uuid::fromString($query->appointmentId)->toRfc4122()),
            sequence: max(0, (int) RowAccessor::nullableInt($row, 'notification_counter') - 1),
            tentative: $status->isPending(),
            startsAtUtc: new \DateTimeImmutable(RowAccessor::string($row, 'starts_at_utc'), new \DateTimeZone('UTC')),
            durationMinutes: RowAccessor::int($row, 'duration_minutes'),
            summary: \sprintf('Rendez-vous de %s — %s', $animal, $clinic->name),
            description: [] === $description ? null : implode("\n", $description),
            location: $clinic->address,
            stampedAt: $this->clock->now(),
        ));
    }
}
