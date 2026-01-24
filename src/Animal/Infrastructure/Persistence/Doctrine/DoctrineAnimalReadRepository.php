<?php

declare(strict_types=1);

namespace App\Animal\Infrastructure\Persistence\Doctrine;

use App\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Animal\Application\Query\GetAnimalById\AuxiliaryContactDto;
use App\Animal\Application\Query\GetAnimalById\IdentificationDto;
use App\Animal\Application\Query\GetAnimalById\LifeCycleDto;
use App\Animal\Application\Query\GetAnimalById\OwnershipDto;
use App\Animal\Application\Query\GetAnimalById\TransferDto;
use App\Animal\Application\Query\SearchAnimals\AnimalListItemView;
use App\Animal\Application\Query\SearchAnimals\SearchAnimalsCriteria;
use App\Animal\Domain\Port\AnimalReadRepositoryInterface;
use App\Animal\Domain\ValueObject\AnimalId;
use App\Animal\Infrastructure\Persistence\Doctrine\Entity\AnimalEntity;
use App\Clinic\Domain\ValueObject\ClinicId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final readonly class DoctrineAnimalReadRepository implements AnimalReadRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function findById(ClinicId $clinicId, AnimalId $animalId): ?AnimalView
    {
        $repository = $this->entityManager->getRepository(AnimalEntity::class);

        $entity = $repository->findOneBy([
            'id'       => $animalId->value(),
            'clinicId' => $clinicId->toString(),
        ]);

        if (null === $entity) {
            return null;
        }

        return $this->mapToAnimalView($entity);
    }

    public function search(ClinicId $clinicId, SearchAnimalsCriteria $criteria): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('a')
            ->from(AnimalEntity::class, 'a')
            ->where('a.clinicId = :clinicId')
            ->setParameter('clinicId', $clinicId->toString())
        ;

        $this->applySearchCriteria($qb, $criteria);

        // Count total
        $countQb = clone $qb;
        $countQb->select('COUNT(DISTINCT a.id)');
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        // Pagination
        $qb->setFirstResult(($criteria->page - 1) * $criteria->limit)
            ->setMaxResults($criteria->limit)
            ->orderBy('a.createdAt', 'DESC')
        ;

        $entities = $qb->getQuery()->getResult();

        /** @phpstan-ignore-next-line argument.type */
        $items = array_values(array_map(fn (AnimalEntity $entity) => $this->mapToAnimalListItemView($entity), $entities));

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    private function applySearchCriteria(QueryBuilder $qb, SearchAnimalsCriteria $criteria): void
    {
        if (null !== $criteria->searchTerm && '' !== $criteria->searchTerm) {
            $qb->andWhere('a.name LIKE :searchTerm')
                ->setParameter('searchTerm', '%' . $criteria->searchTerm . '%')
            ;
        }

        if (null !== $criteria->status) {
            $qb->andWhere('a.status = :status')
                ->setParameter('status', $criteria->status)
            ;
        }

        if (null !== $criteria->species) {
            $qb->andWhere('a.species = :species')
                ->setParameter('species', $criteria->species)
            ;
        }

        if (null !== $criteria->lifeStatus) {
            $qb->andWhere('a.lifeStatus = :lifeStatus')
                ->setParameter('lifeStatus', $criteria->lifeStatus)
            ;
        }

        if (null !== $criteria->ownerClientId) {
            $qb->join('a.ownerships', 'o')
                ->andWhere('o.clientId = :ownerClientId')
                ->andWhere('o.status = :ownershipStatus')
                ->setParameter('ownerClientId', $criteria->ownerClientId)
                ->setParameter('ownershipStatus', 'active')
            ;
        }
    }

    private function mapToAnimalView(AnimalEntity $entity): AnimalView
    {
        $identification = new IdentificationDto(
            microchipNumber: $entity->microchipNumber,
            tattooNumber: $entity->tattooNumber,
            passportNumber: $entity->passportNumber,
            registryType: $entity->registryType,
            registryNumber: $entity->registryNumber,
            sireNumber: $entity->sireNumber,
        );

        $lifeCycle = new LifeCycleDto(
            lifeStatus: $entity->lifeStatus,
            deceasedAt: $entity->deceasedAt?->format(\DateTimeInterface::ATOM),
            missingSince: $entity->missingSince?->format(\DateTimeInterface::ATOM),
        );

        $transfer = new TransferDto(
            transferStatus: $entity->transferStatus,
            soldAt: $entity->soldAt?->format(\DateTimeInterface::ATOM),
            givenAt: $entity->givenAt?->format(\DateTimeInterface::ATOM),
        );

        $auxiliaryContact    = null;
        $hasAuxiliaryContact = null !== $entity->auxiliaryContactFirstName
            && null !== $entity->auxiliaryContactLastName
            && null !== $entity->auxiliaryContactPhoneNumber;

        if ($hasAuxiliaryContact) {
            \assert(null !== $entity->auxiliaryContactFirstName);
            \assert(null !== $entity->auxiliaryContactLastName);
            \assert(null !== $entity->auxiliaryContactPhoneNumber);

            $auxiliaryContact = new AuxiliaryContactDto(
                firstName: $entity->auxiliaryContactFirstName,
                lastName: $entity->auxiliaryContactLastName,
                phoneNumber: $entity->auxiliaryContactPhoneNumber,
            );
        }

        $ownerships = [];
        foreach ($entity->ownerships as $ownershipEntity) {
            $ownerships[] = new OwnershipDto(
                clientId: $ownershipEntity->clientId,
                role: $ownershipEntity->role,
                status: $ownershipEntity->status,
                startedAt: $ownershipEntity->startedAt->format(\DateTimeInterface::ATOM),
                endedAt: $ownershipEntity->endedAt?->format(\DateTimeInterface::ATOM),
            );
        }

        return new AnimalView(
            id: $entity->id,
            clinicId: $entity->clinicId,
            name: $entity->name,
            species: $entity->species,
            sex: $entity->sex,
            reproductiveStatus: $entity->reproductiveStatus,
            isMixedBreed: $entity->isMixedBreed,
            breedName: $entity->breedName,
            birthDate: $entity->birthDate?->format('Y-m-d'),
            color: $entity->color,
            photoUrl: $entity->photoUrl,
            identification: $identification,
            lifeCycle: $lifeCycle,
            transfer: $transfer,
            auxiliaryContact: $auxiliaryContact,
            status: $entity->status,
            ownerships: $ownerships,
            createdAt: $entity->createdAt->format(\DateTimeInterface::ATOM),
            updatedAt: $entity->updatedAt->format(\DateTimeInterface::ATOM),
        );
    }

    private function mapToAnimalListItemView(AnimalEntity $entity): AnimalListItemView
    {
        // Find primary owner
        $primaryOwnerClientId = null;
        foreach ($entity->ownerships as $ownership) {
            if ('primary' === $ownership->role && 'active' === $ownership->status) {
                $primaryOwnerClientId = $ownership->clientId;
                break;
            }
        }

        return new AnimalListItemView(
            id: $entity->id,
            name: $entity->name,
            species: $entity->species,
            sex: $entity->sex,
            breedName: $entity->breedName,
            birthDate: $entity->birthDate?->format('Y-m-d'),
            photoUrl: $entity->photoUrl,
            status: $entity->status,
            lifeStatus: $entity->lifeStatus,
            primaryOwnerClientId: $primaryOwnerClientId,
            createdAt: $entity->createdAt->format(\DateTimeInterface::ATOM),
            updatedAt: $entity->updatedAt->format(\DateTimeInterface::ATOM),
        );
    }
}
