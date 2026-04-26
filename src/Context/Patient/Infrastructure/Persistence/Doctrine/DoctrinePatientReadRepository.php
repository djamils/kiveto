<?php

declare(strict_types=1);

namespace App\Context\Patient\Infrastructure\Persistence\Doctrine;

use App\Context\Patient\Application\Port\PatientReadRepositoryInterface;
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
        $clinicUuid = Uuid::fromString($clinicId);
        $animalUuid = Uuid::fromString($animalId);

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(p.id)')
            ->from(PatientEntity::class, 'p')
            ->where('p.clinicId = :clinicId')
            ->andWhere('p.animalLinkId = :animalLinkId')
            ->andWhere('p.status = :status')
            ->setParameter('clinicId', $clinicUuid, UuidType::NAME)
            ->setParameter('animalLinkId', $animalUuid, UuidType::NAME)
            ->setParameter('status', PatientStatus::Active)
        ;

        $count = (int) $qb->getQuery()->getSingleScalarResult();

        return $count > 0;
    }
}
