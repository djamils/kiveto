<?php

declare(strict_types=1);

namespace App\Animal\Infrastructure\Persistence\Doctrine;

use App\Animal\Domain\Animal;
use App\Animal\Domain\Exception\AnimalNotFound;
use App\Animal\Domain\Port\AnimalRepositoryInterface;
use App\Animal\Domain\ValueObject\AnimalId;
use App\Animal\Infrastructure\Persistence\Doctrine\Entity\AnimalEntity;
use App\Clinic\Domain\ValueObject\ClinicId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineAnimalRepository implements AnimalRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AnimalMapper $mapper,
    ) {
    }

    public function save(Animal $animal): void
    {
        $repository = $this->entityManager->getRepository(AnimalEntity::class);
        $entity     = $repository->find($animal->id()->value());

        if (null === $entity) {
            $entity = $this->mapper->toEntity($animal);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->updateEntity($animal, $entity);
        }

        $this->entityManager->flush();
    }

    public function get(ClinicId $clinicId, AnimalId $animalId): Animal
    {
        $animal = $this->find($clinicId, $animalId);

        if (null === $animal) {
            throw AnimalNotFound::withId($animalId->value());
        }

        return $animal;
    }

    public function find(ClinicId $clinicId, AnimalId $animalId): ?Animal
    {
        $repository = $this->entityManager->getRepository(AnimalEntity::class);

        $entity = $repository->findOneBy([
            'id'       => $animalId->value(),
            'clinicId' => $clinicId->toString(),
        ]);

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }

    public function nextId(): AnimalId
    {
        return AnimalId::fromString(\Symfony\Component\Uid\Uuid::v7()->toString());
    }

    public function existsMicrochip(ClinicId $clinicId, string $microchipNumber, ?AnimalId $exceptAnimalId = null): bool
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(a.id)')
            ->from(AnimalEntity::class, 'a')
            ->where('a.clinicId = :clinicId')
            ->andWhere('a.microchipNumber = :microchipNumber')
            ->setParameter('clinicId', $clinicId->toString())
            ->setParameter('microchipNumber', $microchipNumber)
        ;

        if (null !== $exceptAnimalId) {
            $qb->andWhere('a.id != :exceptId')
                ->setParameter('exceptId', $exceptAnimalId->toString())
            ;
        }

        $count = (int) $qb->getQuery()->getSingleScalarResult();

        return $count > 0;
    }

    public function findByActiveOwner(ClinicId $clinicId, string $clientId): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('DISTINCT a')
            ->from(AnimalEntity::class, 'a')
            ->join('a.ownerships', 'o')
            ->where('a.clinicId = :clinicId')
            ->andWhere('o.clientId = :clientId')
            ->andWhere('o.status = :status')
            ->setParameter('clinicId', $clinicId->toString())
            ->setParameter('clientId', $clientId)
            ->setParameter('status', 'active')
        ;

        $entities = $qb->getQuery()->getResult();

        /* @phpstan-ignore-next-line argument.type */
        return array_values(array_map(fn (AnimalEntity $entity) => $this->mapper->toDomain($entity), $entities));
    }
}
