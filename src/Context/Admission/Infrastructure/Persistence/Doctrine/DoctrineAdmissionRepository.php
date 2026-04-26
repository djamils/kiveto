<?php

declare(strict_types=1);

namespace App\Context\Admission\Infrastructure\Persistence\Doctrine;

use App\Context\Admission\Domain\Admission;
use App\Context\Admission\Domain\Exception\AdmissionNotFoundException;
use App\Context\Admission\Domain\Repository\AdmissionRepositoryInterface;
use App\Context\Admission\Domain\ValueObject\AdmissionId;
use App\Context\Admission\Domain\ValueObject\ClinicId;
use App\Context\Admission\Infrastructure\Persistence\Doctrine\Entity\AdmissionEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineAdmissionRepository implements AdmissionRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AdmissionMapper $mapper,
    ) {
    }

    public function save(Admission $admission): void
    {
        $admissionUuid = Uuid::fromString($admission->id()->value());
        $repository    = $this->entityManager->getRepository(AdmissionEntity::class);
        $entity        = $repository->find($admissionUuid);

        if (null === $entity) {
            $entity = $this->mapper->toEntity($admission);
            $this->entityManager->persist($entity);
        } else {
            $entity->setTriageLevel($admission->triageLevel());
            $entity->setIntakeChannel($admission->intakeChannel());

            $presenter = $admission->presenter();
            if (null !== $presenter) {
                $entity->setPresenterName($presenter->name());
                $entity->setPresenterPhone($presenter->phone()?->toString());
                $entity->setPresenterRole($presenter->role());
            } else {
                $entity->setPresenterName(null);
                $entity->setPresenterPhone(null);
                $entity->setPresenterRole(null);
            }

            $entity->setLocationStatusValue($admission->locationStatus()->value());
            $entity->setLocationStatusEnteredAt($admission->locationStatus()->enteredAt());
            $entity->setStatus($admission->status());
            $entity->setClosureReason($admission->closureReason());
            $entity->setPhysicalDescription($admission->physicalDescription());
            $entity->setTriageNotes($admission->triageNotes());
            $entity->setClosedAt($admission->closedAt());
            $entity->setUpdatedAt($admission->updatedAt());
        }

        $this->entityManager->flush();
    }

    public function get(ClinicId $clinicId, AdmissionId $admissionId): Admission
    {
        $admission = $this->findById($clinicId, $admissionId);

        if (null === $admission) {
            throw new AdmissionNotFoundException($admissionId->value());
        }

        return $admission;
    }

    public function findById(ClinicId $clinicId, AdmissionId $admissionId): ?Admission
    {
        $admissionUuid = Uuid::fromString($admissionId->value());
        $clinicUuid    = Uuid::fromString($clinicId->toString());
        $repository    = $this->entityManager->getRepository(AdmissionEntity::class);

        $entity = $repository->findOneBy([
            'id'       => $admissionUuid,
            'clinicId' => $clinicUuid,
        ]);

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }
}
