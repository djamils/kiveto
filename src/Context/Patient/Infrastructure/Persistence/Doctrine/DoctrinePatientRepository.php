<?php

declare(strict_types=1);

namespace App\Context\Patient\Infrastructure\Persistence\Doctrine;

use App\Context\Patient\Domain\Exception\PatientNotFoundException;
use App\Context\Patient\Domain\Patient;
use App\Context\Patient\Domain\Repository\PatientRepositoryInterface;
use App\Context\Patient\Domain\ValueObject\ClinicId;
use App\Context\Patient\Domain\ValueObject\PatientId;
use App\Context\Patient\Infrastructure\Persistence\Doctrine\Entity\PatientEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrinePatientRepository implements PatientRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PatientMapper $mapper,
    ) {
    }

    public function save(Patient $patient): void
    {
        $patientUuid = Uuid::fromString($patient->id()->toString());
        $repository  = $this->entityManager->getRepository(PatientEntity::class);
        $entity      = $repository->find($patientUuid);

        if (null === $entity) {
            $entity = $this->mapper->toEntity($patient);
            $this->entityManager->persist($entity);
        } else {
            $entity->setStatus($patient->status());
            $entity->setDisplayLabelKind($patient->displayLabel()->kind());
            $entity->setDisplayLabelValue($patient->displayLabel()->value());

            $animalLink = $patient->animalLink();
            if (null !== $animalLink) {
                $entity->setAnimalLinkId(Uuid::fromString($animalLink->animalId()));
            } else {
                $entity->setAnimalLinkId(null);
            }

            $entity->setObservedSpecies($patient->observedSpecies());
            $entity->setObservedColor($patient->observedColor());
            $entity->setUpdatedAt($patient->updatedAt());
        }

        $this->entityManager->flush();
    }

    public function get(ClinicId $clinicId, PatientId $patientId): Patient
    {
        $patient = $this->findById($clinicId, $patientId);

        if (null === $patient) {
            throw new PatientNotFoundException($patientId->toString());
        }

        return $patient;
    }

    public function findById(ClinicId $clinicId, PatientId $patientId): ?Patient
    {
        $patientUuid = Uuid::fromString($patientId->toString());
        $clinicUuid  = Uuid::fromString($clinicId->toString());
        $repository  = $this->entityManager->getRepository(PatientEntity::class);

        $entity = $repository->findOneBy([
            'id'       => $patientUuid,
            'clinicId' => $clinicUuid,
        ]);

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }

    /** @return list<Patient> */
    public function findActiveByAnimalId(ClinicId $clinicId, string $animalId): array
    {
        $clinicUuid = Uuid::fromString($clinicId->toString());
        $animalUuid = Uuid::fromString($animalId);
        $repository = $this->entityManager->getRepository(PatientEntity::class);

        $entities = $repository->findBy([
            'clinicId'     => $clinicUuid,
            'animalLinkId' => $animalUuid,
            'status'       => \App\Context\Patient\Domain\ValueObject\PatientStatus::Active,
        ]);

        $patients = [];
        foreach ($entities as $entity) {
            $patients[] = $this->mapper->toDomain($entity);
        }

        return $patients;
    }
}
