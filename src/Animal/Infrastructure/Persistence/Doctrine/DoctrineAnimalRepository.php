<?php

declare(strict_types=1);

namespace App\Animal\Infrastructure\Persistence\Doctrine;

use App\Animal\Domain\Animal;
use App\Animal\Domain\Exception\AnimalNotFoundException;
use App\Animal\Domain\Repository\AnimalRepositoryInterface;
use App\Animal\Domain\ValueObject\AnimalId;
use App\Animal\Infrastructure\Persistence\Doctrine\Entity\AnimalEntity;
use App\Clinic\Domain\ValueObject\ClinicId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineAnimalRepository implements AnimalRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AnimalMapper $mapper,
    ) {
    }

    public function save(Animal $animal): void
    {
        $animalUuid = Uuid::fromString($animal->id()->toString());
        $repository = $this->entityManager->getRepository(AnimalEntity::class);
        $entity     = $repository->find($animalUuid);

        if (null === $entity) {
            $entity = $this->mapper->toEntity($animal);
            $this->entityManager->persist($entity);
        } else {
            // For updates, we need to clear and rebuild to maintain proper cascade
            $this->entityManager->remove($entity);
            $this->entityManager->flush();

            $entity = $this->mapper->toEntity($animal);
            $this->entityManager->persist($entity);
        }

        $this->entityManager->flush();
    }

    public function get(ClinicId $clinicId, AnimalId $animalId): Animal
    {
        $animal = $this->findById($clinicId, $animalId);

        if (null === $animal) {
            throw AnimalNotFoundException::withId($animalId->toString());
        }

        return $animal;
    }

    public function findById(ClinicId $clinicId, AnimalId $animalId): ?Animal
    {
        $animalUuid = Uuid::fromString($animalId->toString());
        $clinicUuid = Uuid::fromString($clinicId->toString());
        $repository = $this->entityManager->getRepository(AnimalEntity::class);

        $entity = $repository->findOneBy([
            'id'       => $animalUuid,
            'clinicId' => $clinicUuid,
        ]);

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }

    public function existsByMicrochip(
        ClinicId $clinicId,
        string $microchipNumber,
        ?AnimalId $exceptAnimalId = null,
    ): bool {
        $clinicUuid = Uuid::fromString($clinicId->toString());
        $repository = $this->entityManager->getRepository(AnimalEntity::class);

        if (null === $exceptAnimalId) {
            $count = $repository->count([
                'clinicId'        => $clinicUuid,
                'microchipNumber' => $microchipNumber,
            ]);

            return $count > 0;
        }

        // When we need to exclude an animal, we still need QueryBuilder
        $exceptUuid = Uuid::fromString($exceptAnimalId->toString());
        $qb         = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(a.id)')
            ->from(AnimalEntity::class, 'a')
            ->where('a.clinicId = :clinicId')
            ->andWhere('a.microchipNumber = :microchipNumber')
            ->andWhere('a.id != :exceptId')
            ->setParameter('clinicId', $clinicUuid)
            ->setParameter('microchipNumber', $microchipNumber)
            ->setParameter('exceptId', $exceptUuid)
        ;

        $count = (int) $qb->getQuery()->getSingleScalarResult();

        return $count > 0;
    }

    public function findByActiveOwner(ClinicId $clinicId, string $clientId): array
    {
        $clinicUuid = Uuid::fromString($clinicId->toString());
        $clientUuid = Uuid::fromString($clientId);

        // First, find all animal IDs for this client with active ownership
        $ownershipRepository = $this->entityManager->getRepository(Entity\OwnershipEntity::class);
        $ownerships          = $ownershipRepository->findBy([
            'clientId' => $clientUuid,
            'status'   => 'active',
        ]);

        if (empty($ownerships)) {
            return [];
        }

        // Extract animal IDs and filter by clinicId
        $animalIds = [];
        foreach ($ownerships as $ownership) {
            $animal = $ownership->getAnimal();
            if ($animal && $animal->getClinicId()->equals($clinicUuid)) {
                $animalIds[] = $animal->getId();
            }
        }

        if (empty($animalIds)) {
            return [];
        }

        // Now fetch all animals with these IDs
        $repository = $this->entityManager->getRepository(AnimalEntity::class);
        $entities   = $repository->findBy(['id' => $animalIds]);

        /* @phpstan-ignore-next-line argument.type */
        return array_values(array_map(fn (AnimalEntity $entity) => $this->mapper->toDomain($entity), $entities));
    }
}
