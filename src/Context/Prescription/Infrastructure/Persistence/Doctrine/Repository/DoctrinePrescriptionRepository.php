<?php

declare(strict_types=1);

namespace App\Context\Prescription\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Prescription\Domain\Exception\PrescriptionNotFoundException;
use App\Context\Prescription\Domain\Prescription;
use App\Context\Prescription\Domain\Repository\PrescriptionRepositoryInterface;
use App\Context\Prescription\Domain\ValueObject\ClinicId;
use App\Context\Prescription\Domain\ValueObject\ConsultationId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionId;
use App\Context\Prescription\Domain\ValueObject\PrescriptionStatus;
use App\Context\Prescription\Infrastructure\Persistence\Doctrine\Entity\PrescriptionEntity;
use App\Context\Prescription\Infrastructure\Persistence\Doctrine\Entity\PrescriptionLineEntity;
use App\Context\Prescription\Infrastructure\Persistence\Doctrine\Entity\RenewalEntity;
use App\Context\Prescription\Infrastructure\Persistence\Doctrine\PrescriptionMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrinePrescriptionRepository implements PrescriptionRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PrescriptionMapper $mapper,
    ) {
    }

    public function save(Prescription $prescription): void
    {
        $uuid   = Uuid::fromString($prescription->id()->toString());
        $entity = $this->entityManager->getRepository(PrescriptionEntity::class)->find($uuid);

        if (null === $entity) {
            $entity = $this->mapper->toEntity($prescription);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->applyMutableState($prescription, $entity);
        }

        $this->replaceLines($prescription, $uuid);
        $this->appendRenewals($prescription, $uuid);

        $this->entityManager->flush();
    }

    public function get(ClinicId $clinicId, PrescriptionId $prescriptionId): Prescription
    {
        $prescription = $this->findById($clinicId, $prescriptionId);

        if (null === $prescription) {
            throw new PrescriptionNotFoundException($prescriptionId->toString());
        }

        return $prescription;
    }

    public function findById(ClinicId $clinicId, PrescriptionId $prescriptionId): ?Prescription
    {
        $entity = $this->entityManager->getRepository(PrescriptionEntity::class)->findOneBy([
            'id'       => Uuid::fromString($prescriptionId->toString()),
            'clinicId' => Uuid::fromString($clinicId->toString()),
        ]);

        return null === $entity ? null : $this->hydrate($entity);
    }

    public function findForConsultation(ClinicId $clinicId, ConsultationId $consultationId): ?Prescription
    {
        /** @var list<PrescriptionEntity> $entities */
        $entities = $this->entityManager->getRepository(PrescriptionEntity::class)->findBy(
            [
                'clinicId'       => Uuid::fromString($clinicId->toString()),
                'consultationId' => Uuid::fromString($consultationId->toString()),
            ],
            ['createdAt' => 'DESC'],
        );

        foreach ($entities as $entity) {
            if (PrescriptionStatus::CANCELLED !== $entity->getStatus()) {
                return $this->hydrate($entity);
            }
        }

        return null;
    }

    private function hydrate(PrescriptionEntity $entity): Prescription
    {
        /** @var list<PrescriptionLineEntity> $lines */
        $lines = $this->entityManager->getRepository(PrescriptionLineEntity::class)->findBy(
            ['prescriptionId' => $entity->getId()],
            ['position' => 'ASC'],
        );

        /** @var list<RenewalEntity> $renewals */
        $renewals = $this->entityManager->getRepository(RenewalEntity::class)->findBy(
            ['prescriptionId' => $entity->getId()],
            ['position' => 'ASC'],
        );

        return $this->mapper->toDomain($entity, $lines, $renewals);
    }

    /**
     * Lines only move while the prescription is a draft, and a draft is rebuilt
     * wholesale, so replacing them is both correct and cheaper than diffing.
     */
    private function replaceLines(Prescription $prescription, Uuid $prescriptionUuid): void
    {
        $repository = $this->entityManager->getRepository(PrescriptionLineEntity::class);

        $keptIds = array_map(
            static fn ($line): string => $line->id()->toString(),
            $prescription->lines(),
        );

        /** @var list<PrescriptionLineEntity> $existing */
        $existing = $repository->findBy(['prescriptionId' => $prescriptionUuid]);

        foreach ($existing as $entity) {
            if (!\in_array($entity->getId()->toRfc4122(), $keptIds, true)) {
                $this->entityManager->remove($entity);
            }
        }

        $existingIds = array_map(
            static fn (PrescriptionLineEntity $entity): string => $entity->getId()->toRfc4122(),
            $existing,
        );

        foreach ($prescription->lines() as $position => $line) {
            if (\in_array($line->id()->toString(), $existingIds, true)) {
                continue;
            }

            $this->entityManager->persist($this->mapper->lineToEntity($line, $prescription->id(), $position));
        }
    }

    private function appendRenewals(Prescription $prescription, Uuid $prescriptionUuid): void
    {
        $repository = $this->entityManager->getRepository(RenewalEntity::class);

        /** @var list<RenewalEntity> $existing */
        $existing = $repository->findBy(['prescriptionId' => $prescriptionUuid]);

        $existingIds = array_map(
            static fn (RenewalEntity $entity): string => $entity->getId()->toRfc4122(),
            $existing,
        );

        foreach ($prescription->renewalHistory() as $position => $renewal) {
            if (\in_array($renewal->id()->toString(), $existingIds, true)) {
                continue;
            }

            $this->entityManager->persist(
                $this->mapper->renewalToEntity($renewal, $prescription->id(), $position),
            );
        }
    }
}
