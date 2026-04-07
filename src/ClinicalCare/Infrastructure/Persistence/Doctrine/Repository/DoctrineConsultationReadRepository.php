<?php

declare(strict_types=1);

namespace App\ClinicalCare\Infrastructure\Persistence\Doctrine\Repository;

use App\ClinicalCare\Application\Port\ConsultationReadRepositoryInterface;
use App\ClinicalCare\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
use App\ClinicalCare\Domain\ValueObject\ConsultationId;
use App\Shared\Infrastructure\Persistence\DbalRow;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineConsultationReadRepository implements ConsultationReadRepositoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function findById(ConsultationId $consultationId): ConsultationDetailsDTO
    {
        $uuid                 = Uuid::fromString($consultationId->toString());
        $consultationIdBinary = $uuid->toBinary();

        // Fetch consultation
        $sql          = 'SELECT * FROM clinical_care__consultations WHERE id = :id';
        $consultation = $this->connection->fetchAssociative($sql, [
            'id' => $consultationIdBinary,
        ]);

        if (false === $consultation) {
            throw new \DomainException(\sprintf(
                'Consultation "%s" not found.',
                $consultationId->toString()
            ));
        }

        // Fetch notes
        $sqlNotes = 'SELECT note_type, content, created_at_utc 
                     FROM clinical_care__clinical_notes 
                     WHERE consultation_id = :consultationId 
                     ORDER BY created_at_utc ASC';
        $notesResult = $this->connection->fetchAllAssociative($sqlNotes, [
            'consultationId' => $consultationIdBinary,
        ]);
        $notes = array_map(
            /**
             * @param array<string, mixed> $row
             *
             * @return array{noteType: string, content: string, createdAt: string}
             */
            fn (array $row): array => [
                'noteType'  => DbalRow::string($row, 'note_type'),
                'content'   => DbalRow::string($row, 'content'),
                'createdAt' => DbalRow::string($row, 'created_at_utc'),
            ],
            $notesResult,
        );

        // Fetch acts
        $sqlActs = 'SELECT label, quantity, performed_at_utc 
                    FROM clinical_care__performed_acts 
                    WHERE consultation_id = :consultationId 
                    ORDER BY performed_at_utc ASC';
        $actsResult = $this->connection->fetchAllAssociative($sqlActs, [
            'consultationId' => $consultationIdBinary,
        ]);
        $acts = array_map(
            /**
             * @param array<string, mixed> $row
             *
             * @return array{label: string, quantity: string, performedAt: string}
             */
            fn (array $row): array => [
                'label'       => DbalRow::string($row, 'label'),
                'quantity'    => DbalRow::string($row, 'quantity'),
                'performedAt' => DbalRow::string($row, 'performed_at_utc'),
            ],
            $actsResult,
        );

        // Build vitals array if present
        $weightKg     = DbalRow::nullableString($consultation, 'weight_kg');
        $temperatureC = DbalRow::nullableString($consultation, 'temperature_c');
        $vitals       = null;
        if (null !== $weightKg || null !== $temperatureC) {
            $vitals = [
                'weightKg'     => $weightKg,
                'temperatureC' => $temperatureC,
            ];
        }

        return new ConsultationDetailsDTO(
            consultationId: DbalRow::uuid($consultation, 'id'),
            clinicId: DbalRow::uuid($consultation, 'clinic_id'),
            practitionerUserId: DbalRow::uuid($consultation, 'practitioner_user_id'),
            status: DbalRow::string($consultation, 'status'),
            appointmentId: DbalRow::nullableUuid($consultation, 'appointment_id'),
            waitingRoomEntryId: DbalRow::nullableUuid($consultation, 'waiting_room_entry_id'),
            ownerId: DbalRow::nullableUuid($consultation, 'owner_id'),
            animalId: DbalRow::nullableUuid($consultation, 'animal_id'),
            chiefComplaint: DbalRow::nullableString($consultation, 'chief_complaint'),
            vitals: $vitals,
            notes: $notes,
            acts: $acts,
            summary: DbalRow::nullableString($consultation, 'summary'),
            startedAtUtc: DbalRow::string($consultation, 'started_at_utc'),
            closedAtUtc: DbalRow::nullableString($consultation, 'closed_at_utc'),
        );
    }
}
