<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Consultation\Domain\Consultation;
use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\BillingLineEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\ClinicalNoteEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\ConsultationChildEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\ConsultationEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\DiagnosisEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\ExamSystemEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\MotifEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\PerformedActEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\PlanActionEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\PrescriptionLineEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Entity\TypedVitalEntity;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Mapper\BillingLineMapper;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Mapper\ClinicalNoteMapper;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Mapper\ConsultationMapper;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Mapper\DiagnosisMapper;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Mapper\ExamSystemMapper;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Mapper\MotifMapper;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Mapper\PerformedActMapper;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Mapper\PlanActionMapper;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Mapper\PrescriptionLineMapper;
use App\Context\Consultation\Infrastructure\Persistence\Doctrine\Mapper\TypedVitalMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineConsultationRepository implements ConsultationRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private ConsultationMapper $mapper,
        private ClinicalNoteMapper $noteMapper,
        private PerformedActMapper $actMapper,
        private MotifMapper $motifMapper,
        private TypedVitalMapper $typedVitalMapper,
        private ExamSystemMapper $examSystemMapper,
        private DiagnosisMapper $diagnosisMapper,
        private PlanActionMapper $planActionMapper,
        private PrescriptionLineMapper $prescriptionLineMapper,
        private BillingLineMapper $billingLineMapper,
    ) {
    }

    public function save(Consultation $consultation): void
    {
        $consultationIdBinary = Uuid::fromString($consultation->getId()->toString())->toBinary();

        $existingEntity     = $this->em->find(ConsultationEntity::class, $consultationIdBinary);
        $consultationEntity = $this->mapper->toEntity($consultation, $existingEntity);

        if (null === $existingEntity) {
            $this->em->persist($consultationEntity);
        }

        $this->syncChildren(
            ClinicalNoteEntity::class,
            $consultationIdBinary,
            $consultation->getNotes(),
            static fn ($note): string => $note->getId(),
            fn ($note, int $position, ?ClinicalNoteEntity $entity): ClinicalNoteEntity => $this->noteMapper->toEntity(
                $note,
                $consultationIdBinary,
                $entity,
            ),
        );

        $this->syncChildren(
            PerformedActEntity::class,
            $consultationIdBinary,
            $consultation->getActs(),
            static fn ($act): string => $act->getId(),
            fn ($act, int $position, ?PerformedActEntity $entity): PerformedActEntity => $this->actMapper->toEntity(
                $act,
                $consultationIdBinary,
                $entity,
            ),
        );

        $this->syncChildren(
            MotifEntity::class,
            $consultationIdBinary,
            $consultation->getMotifs(),
            static fn ($motif): string => $motif->getId(),
            fn ($motif, int $position, ?MotifEntity $entity): MotifEntity => $this->motifMapper->toEntity(
                $motif,
                $consultationIdBinary,
                $position,
                $entity,
            ),
        );

        $this->syncChildren(
            TypedVitalEntity::class,
            $consultationIdBinary,
            $consultation->getTypedVitals(),
            static fn ($vital): string => $vital->getId(),
            fn ($vital, int $position, ?TypedVitalEntity $entity): TypedVitalEntity => $this->typedVitalMapper->toEntity(
                $vital,
                $consultationIdBinary,
                $position,
                $entity,
            ),
        );

        $this->syncChildren(
            ExamSystemEntity::class,
            $consultationIdBinary,
            $consultation->getExamSystems(),
            static fn ($exam): string => $exam->getId(),
            fn ($exam, int $position, ?ExamSystemEntity $entity): ExamSystemEntity => $this->examSystemMapper->toEntity(
                $exam,
                $consultationIdBinary,
                $position,
                $entity,
            ),
        );

        $this->syncChildren(
            DiagnosisEntity::class,
            $consultationIdBinary,
            $consultation->getDiagnoses(),
            static fn ($diagnosis): string => $diagnosis->getId(),
            fn ($diagnosis, int $position, ?DiagnosisEntity $entity): DiagnosisEntity => $this->diagnosisMapper
                ->toEntity($diagnosis, $consultationIdBinary, $position, $entity),
        );

        $this->syncChildren(
            PlanActionEntity::class,
            $consultationIdBinary,
            $consultation->getPlanActions(),
            static fn ($action): string => $action->getId(),
            fn ($action, int $position, ?PlanActionEntity $entity): PlanActionEntity => $this->planActionMapper
                ->toEntity($action, $consultationIdBinary, $position, $entity),
        );

        $this->syncChildren(
            PrescriptionLineEntity::class,
            $consultationIdBinary,
            $consultation->getPrescriptionLines(),
            static fn ($line): string => $line->getId(),
            fn ($line, int $position, ?PrescriptionLineEntity $entity): PrescriptionLineEntity => $this
                ->prescriptionLineMapper->toEntity($line, $consultationIdBinary, $position, $entity),
        );

        $this->syncChildren(
            BillingLineEntity::class,
            $consultationIdBinary,
            $consultation->getBillingLines(),
            static fn ($line): string => $line->getId(),
            fn ($line, int $position, ?BillingLineEntity $entity): BillingLineEntity => $this->billingLineMapper
                ->toEntity($line, $consultationIdBinary, $position, $entity),
        );

        $this->em->flush();
    }

    public function findById(ConsultationId $id): ?Consultation
    {
        $consultationIdBinary = Uuid::fromString($id->toString())->toBinary();

        /** @var ConsultationEntity|null $entity */
        $entity = $this->em->find(ConsultationEntity::class, $consultationIdBinary);

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain(
            $entity,
            $this->loadChildren(ClinicalNoteEntity::class, $consultationIdBinary, ['createdAtUtc' => 'ASC']),
            $this->loadChildren(PerformedActEntity::class, $consultationIdBinary, ['performedAtUtc' => 'ASC']),
            $this->loadChildren(MotifEntity::class, $consultationIdBinary, ['position' => 'ASC']),
            $this->loadChildren(TypedVitalEntity::class, $consultationIdBinary, ['position' => 'ASC']),
            $this->loadChildren(ExamSystemEntity::class, $consultationIdBinary, ['position' => 'ASC']),
            $this->loadChildren(DiagnosisEntity::class, $consultationIdBinary, ['position' => 'ASC']),
            $this->loadChildren(PlanActionEntity::class, $consultationIdBinary, ['position' => 'ASC']),
            $this->loadChildren(PrescriptionLineEntity::class, $consultationIdBinary, ['position' => 'ASC']),
            $this->loadChildren(BillingLineEntity::class, $consultationIdBinary, ['position' => 'ASC']),
        );
    }

    /**
     * @template TEntity of ConsultationChildEntity
     *
     * @param class-string<TEntity>       $entityClass
     * @param array<string, 'ASC'|'DESC'> $orderBy
     *
     * @return list<TEntity>
     */
    private function loadChildren(string $entityClass, string $consultationIdBinary, array $orderBy): array
    {
        return $this->em->getRepository($entityClass)->findBy(
            ['consultationId' => $consultationIdBinary],
            $orderBy,
        );
    }

    /**
     * Synchronises a child collection by row id.
     *
     * Rows already managed are updated in place (which also avoids Doctrine's
     * identity-map collision on a delete-then-reinsert of the same primary key),
     * new records are persisted, and rows the aggregate no longer holds are
     * removed.
     *
     * @template TEntity of ConsultationChildEntity
     * @template TRecord of object
     *
     * @param class-string<TEntity>                     $entityClass
     * @param array<TRecord>                            $records
     * @param callable(TRecord): string                 $idOf
     * @param callable(TRecord, int, ?TEntity): TEntity $map
     */
    private function syncChildren(
        string $entityClass,
        string $consultationIdBinary,
        array $records,
        callable $idOf,
        callable $map,
    ): void {
        $managed = [];

        foreach ($this->loadChildren($entityClass, $consultationIdBinary, []) as $childEntity) {
            $managed[$childEntity->getId()] = $childEntity;
        }

        $position = 0;

        foreach ($records as $record) {
            $idBinary = Uuid::fromString($idOf($record))->toBinary();
            $existing = $managed[$idBinary] ?? null;
            $entity   = $map($record, $position, $existing);

            if (null === $existing) {
                $this->em->persist($entity);
            }

            unset($managed[$idBinary]);
            ++$position;
        }

        foreach ($managed as $orphan) {
            $this->em->remove($orphan);
        }
    }
}
