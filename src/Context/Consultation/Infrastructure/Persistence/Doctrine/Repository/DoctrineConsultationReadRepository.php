<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineConsultationReadRepository implements ConsultationReadRepositoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function findById(ConsultationId $consultationId, ClinicId $clinicId): ConsultationDetailsDTO
    {
        $consultationIdBinary = Uuid::fromString($consultationId->toString())->toBinary();
        $clinicIdBinary       = Uuid::fromString($clinicId->toString())->toBinary();

        // Fetch consultation
        $sql          = 'SELECT * FROM consultation__consultations WHERE id = :id AND clinic_id = :clinicId';
        $consultation = $this->connection->fetchAssociative($sql, [
            'id'       => $consultationIdBinary,
            'clinicId' => $clinicIdBinary,
        ]);

        if (false === $consultation) {
            throw new \DomainException(\sprintf(
                'Consultation "%s" not found.',
                $consultationId->toString()
            ));
        }

        // Fetch notes
        $sqlNotes = 'SELECT note_type, content, created_at_utc
                     FROM consultation__clinical_notes
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
                'noteType'  => RowAccessor::string($row, 'note_type'),
                'content'   => RowAccessor::string($row, 'content'),
                'createdAt' => RowAccessor::string($row, 'created_at_utc'),
            ],
            $notesResult,
        );

        // Fetch acts
        $sqlActs = 'SELECT label, quantity, performed_at_utc
                    FROM consultation__performed_acts
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
                'label'       => RowAccessor::string($row, 'label'),
                'quantity'    => RowAccessor::string($row, 'quantity'),
                'performedAt' => RowAccessor::string($row, 'performed_at_utc'),
            ],
            $actsResult,
        );

        // Build vitals array if present
        $weightKg     = RowAccessor::nullableString($consultation, 'weight_kg');
        $temperatureC = RowAccessor::nullableString($consultation, 'temperature_c');
        $vitals       = null;
        if (null !== $weightKg || null !== $temperatureC) {
            $vitals = [
                'weightKg'     => $weightKg,
                'temperatureC' => $temperatureC,
            ];
        }

        return new ConsultationDetailsDTO(
            consultationId: RowAccessor::uuid($consultation, 'id'),
            clinicId: RowAccessor::uuid($consultation, 'clinic_id'),
            practitionerUserId: RowAccessor::uuid($consultation, 'practitioner_user_id'),
            status: RowAccessor::string($consultation, 'status'),
            appointmentId: RowAccessor::nullableUuid($consultation, 'appointment_id'),
            admissionId: RowAccessor::uuid($consultation, 'admission_id'),
            patientId: RowAccessor::uuid($consultation, 'patient_id'),
            chiefComplaint: RowAccessor::nullableString($consultation, 'chief_complaint'),
            vitals: $vitals,
            notes: $notes,
            acts: $acts,
            summary: RowAccessor::nullableString($consultation, 'summary'),
            startedAtUtc: RowAccessor::string($consultation, 'started_at_utc'),
            closedAtUtc: RowAccessor::nullableString($consultation, 'closed_at_utc'),
        );
    }
}
