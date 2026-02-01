<?php

declare(strict_types=1);

namespace App\ClinicalCare\Infrastructure\Persistence\Doctrine\Repository;

use App\ClinicalCare\Application\Port\ConsultationReadRepositoryInterface;
use App\ClinicalCare\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
use App\ClinicalCare\Domain\ValueObject\ConsultationId;
use App\ClinicalCare\Infrastructure\Persistence\Doctrine\Entity\ClinicalNoteEntity;
use App\ClinicalCare\Infrastructure\Persistence\Doctrine\Entity\ConsultationEntity;
use App\ClinicalCare\Infrastructure\Persistence\Doctrine\Entity\PerformedActEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineConsultationReadRepository implements ConsultationReadRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function findById(ConsultationId $consultationId): ConsultationDetailsDTO
    {
        $repository = $this->entityManager->getRepository(ConsultationEntity::class);

        // Use Doctrine find with binary UUID (entity ID is stored as BINARY(16))
        $uuid = Uuid::fromString($consultationId->toString());
        $consultation = $repository->find($uuid->toBinary());

        if (null === $consultation) {
            throw new \DomainException(\sprintf(
                'Consultation "%s" not found.',
                $consultationId->toString()
            ));
        }

        // Fetch notes with QueryBuilder
        $notesRepo = $this->entityManager->getRepository(ClinicalNoteEntity::class);
        $qbNotes   = $notesRepo->createQueryBuilder('n');
        $qbNotes->where('n.consultationId = :consultationId')
            ->orderBy('n.createdAtUtc', 'ASC')
            ->setParameter('consultationId', Uuid::fromString($consultationId->toString()));

        $notesEntities = $qbNotes->getQuery()->getResult();
        $notes         = \array_map(fn (ClinicalNoteEntity $note) => [
            'noteType'  => $note->getNoteType()->value,
            'content'   => $note->getContent(),
            'createdAt' => $note->getCreatedAtUtc()->format('Y-m-d H:i:s'),
        ], $notesEntities);

        // Fetch acts with QueryBuilder
        $actsRepo = $this->entityManager->getRepository(PerformedActEntity::class);
        $qbActs   = $actsRepo->createQueryBuilder('a');
        $qbActs->where('a.consultationId = :consultationId')
            ->orderBy('a.performedAtUtc', 'ASC')
            ->setParameter('consultationId', Uuid::fromString($consultationId->toString()));

        $actsEntities = $qbActs->getQuery()->getResult();
        $acts         = \array_map(fn (PerformedActEntity $act) => [
            'label'       => $act->getLabel(),
            'quantity'    => $act->getQuantity(),
            'performedAt' => $act->getPerformedAtUtc()->format('Y-m-d H:i:s'),
        ], $actsEntities);

        // Build vitals array if present
        $vitals = null;
        if (null !== $consultation->getWeightKg() || null !== $consultation->getTemperatureC()) {
            $vitals = [
                'weightKg'     => $consultation->getWeightKg(),
                'temperatureC' => $consultation->getTemperatureC(),
            ];
        }

        return new ConsultationDetailsDTO(
            consultationId: $consultation->getId(),
            clinicId: $consultation->getClinicId(),
            practitionerUserId: $consultation->getPractitionerUserId(),
            status: $consultation->getStatus(),
            appointmentId: $consultation->getAppointmentId(),
            waitingRoomEntryId: $consultation->getWaitingRoomEntryId(),
            ownerId: $consultation->getOwnerId(),
            animalId: $consultation->getAnimalId(),
            chiefComplaint: $consultation->getChiefComplaint(),
            vitals: $vitals,
            notes: $notes,
            acts: $acts,
            summary: $consultation->getSummary(),
            startedAtUtc: $consultation->getStartedAtUtc()->format('Y-m-d H:i:s'),
            closedAtUtc: $consultation->getClosedAtUtc()?->format('Y-m-d H:i:s'),
        );
    }
}
