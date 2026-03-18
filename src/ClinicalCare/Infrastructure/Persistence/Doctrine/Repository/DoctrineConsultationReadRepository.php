<?php

declare(strict_types=1);

namespace App\ClinicalCare\Infrastructure\Persistence\Doctrine\Repository;

use App\ClinicalCare\Application\Port\ConsultationReadRepositoryInterface;
use App\ClinicalCare\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
use App\ClinicalCare\Domain\ValueObject\ConsultationId;
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
        $uuid = Uuid::fromString($consultationId->toString());
        $consultationIdBinary = $uuid->toBinary();

        // Fetch consultation
        $sql = 'SELECT * FROM clinical_care__consultations WHERE id = :id';
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
        $notes = array_map(fn (array $row) => [
            'noteType'  => $row['note_type'],
            'content'   => $row['content'],
            'createdAt' => $row['created_at_utc'],
        ], $notesResult);

        // Fetch acts
        $sqlActs = 'SELECT label, quantity, performed_at_utc 
                    FROM clinical_care__performed_acts 
                    WHERE consultation_id = :consultationId 
                    ORDER BY performed_at_utc ASC';
        $actsResult = $this->connection->fetchAllAssociative($sqlActs, [
            'consultationId' => $consultationIdBinary,
        ]);
        $acts = array_map(fn (array $row) => [
            'label'       => $row['label'],
            'quantity'    => $row['quantity'],
            'performedAt' => $row['performed_at_utc'],
        ], $actsResult);

        // Build vitals array if present
        $vitals = null;
        if (null !== $consultation['weight_kg'] || null !== $consultation['temperature_c']) {
            $vitals = [
                'weightKg'     => $consultation['weight_kg'],
                'temperatureC' => $consultation['temperature_c'],
            ];
        }

        return new ConsultationDetailsDTO(
            consultationId: Uuid::fromBinary($consultation['id'])->toString(),
            clinicId: Uuid::fromBinary($consultation['clinic_id'])->toString(),
            practitionerUserId: Uuid::fromBinary($consultation['practitioner_user_id'])->toString(),
            status: $consultation['status'],
            appointmentId: $consultation['appointment_id'] ? Uuid::fromBinary($consultation['appointment_id'])->toString() : null,
            waitingRoomEntryId: $consultation['waiting_room_entry_id'] ? Uuid::fromBinary($consultation['waiting_room_entry_id'])->toString() : null,
            ownerId: $consultation['owner_id'] ? Uuid::fromBinary($consultation['owner_id'])->toString() : null,
            animalId: $consultation['animal_id'] ? Uuid::fromBinary($consultation['animal_id'])->toString() : null,
            chiefComplaint: $consultation['chief_complaint'],
            vitals: $vitals,
            notes: $notes,
            acts: $acts,
            summary: $consultation['summary'],
            startedAtUtc: $consultation['started_at_utc'],
            closedAtUtc: $consultation['closed_at_utc'],
        );
    }
}
