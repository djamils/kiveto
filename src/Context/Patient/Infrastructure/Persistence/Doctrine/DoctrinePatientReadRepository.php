<?php

declare(strict_types=1);

namespace App\Context\Patient\Infrastructure\Persistence\Doctrine;

use App\Context\Patient\Application\Port\PatientReadRepositoryInterface;
use App\Context\Patient\Application\Query\GetPatientAnimalLink\PatientAnimalLinkDto;
use App\Context\Patient\Domain\ValueObject\PatientStatus;
use App\Context\Patient\Infrastructure\Persistence\Doctrine\Entity\PatientEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrinePatientReadRepository implements PatientReadRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function existsActiveForAnimal(string $clinicId, string $animalId): bool
    {
        return null !== $this->getActivePatientIdForAnimal($clinicId, $animalId);
    }

    public function getActivePatientIdForAnimal(string $clinicId, string $animalId): ?string
    {
        $clinicUuid = Uuid::fromString($clinicId);
        $animalUuid = Uuid::fromString($animalId);

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('p.id')
            ->from(PatientEntity::class, 'p')
            ->where('p.clinicId = :clinicId')
            ->andWhere('p.animalLinkId = :animalLinkId')
            ->andWhere('p.status = :status')
            ->setParameter('clinicId', $clinicUuid, UuidType::NAME)
            ->setParameter('animalLinkId', $animalUuid, UuidType::NAME)
            ->setParameter('status', PatientStatus::Active)
            ->setMaxResults(1)
        ;

        /** @var array{id: Uuid}|null $row */
        $row = $qb->getQuery()->getOneOrNullResult();

        return null !== $row ? $row['id']->toString() : null;
    }

    public function findAnimalLink(string $clinicId, string $patientId): ?PatientAnimalLinkDto
    {
        $clinicUuid  = Uuid::fromString($clinicId);
        $patientUuid = Uuid::fromString($patientId);

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('p.id', 'p.animalLinkId', 'p.displayLabelValue', 'p.observedSpecies', 'p.observedColor')
            ->from(PatientEntity::class, 'p')
            ->where('p.id = :id')
            ->andWhere('p.clinicId = :clinicId')
            ->setParameter('id', $patientUuid, UuidType::NAME)
            ->setParameter('clinicId', $clinicUuid, UuidType::NAME)
        ;

        /** @var array{id: Uuid, animalLinkId: Uuid|null, displayLabelValue: string, observedSpecies: string|null, observedColor: string|null}|null $row */
        $row = $qb->getQuery()->getOneOrNullResult();

        if (null === $row) {
            return null;
        }

        return new PatientAnimalLinkDto(
            patientId: $row['id']->toString(),
            animalId: $row['animalLinkId']?->toString(),
            displayLabel: $row['displayLabelValue'],
            observedSpecies: $row['observedSpecies'],
            observedColor: $row['observedColor'],
        );
    }
}
