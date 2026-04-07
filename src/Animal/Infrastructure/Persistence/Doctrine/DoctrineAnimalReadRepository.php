<?php

declare(strict_types=1);

namespace App\Animal\Infrastructure\Persistence\Doctrine;

use App\Animal\Application\Port\AnimalReadRepositoryInterface;
use App\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Animal\Application\Query\GetAnimalById\AuxiliaryContactDto;
use App\Animal\Application\Query\GetAnimalById\IdentificationDto;
use App\Animal\Application\Query\GetAnimalById\LifeCycleDto;
use App\Animal\Application\Query\GetAnimalById\OwnershipDto;
use App\Animal\Application\Query\GetAnimalById\TransferDto;
use App\Animal\Application\Query\SearchAnimals\AnimalListItemView;
use App\Animal\Application\Query\SearchAnimals\SearchAnimalsCriteria;
use App\Animal\Domain\ValueObject\AnimalId;
use App\Animal\Domain\ValueObject\OwnershipRole;
use App\Animal\Domain\ValueObject\OwnershipStatus;
use App\Animal\Infrastructure\Persistence\Doctrine\Entity\AnimalEntity;
use App\Clinic\Domain\ValueObject\ClinicId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineAnimalReadRepository implements AnimalReadRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function findById(ClinicId $clinicId, AnimalId $animalId): ?AnimalView
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

        return $this->mapToAnimalView($entity);
    }

    public function search(ClinicId $clinicId, SearchAnimalsCriteria $criteria): array
    {
        $clinicUuid = Uuid::fromString($clinicId->toString());
        $qb         = $this->entityManager->createQueryBuilder();
        $qb->select('a')
            ->from(AnimalEntity::class, 'a')
            ->where('a.clinicId = :clinicId')
            ->setParameter('clinicId', $clinicUuid, UuidType::NAME)
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
        $items = array_values(array_map(fn (AnimalEntity $entity) => $this->mapToAnimalListItemView($entity), $entities)); // phpcs:ignore

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
            $clientUuid = Uuid::fromString($criteria->ownerClientId);
            $qb->join('a.ownerships', 'o')
                ->andWhere('o.clientId = :ownerClientId')
                ->andWhere('o.status = :ownershipStatus')
                ->setParameter('ownerClientId', $clientUuid, UuidType::NAME)
                ->setParameter('ownershipStatus', OwnershipStatus::ACTIVE)
            ;
        }
    }

    private function mapToAnimalView(AnimalEntity $entity): AnimalView
    {
        $identification = new IdentificationDto(
            microchipNumber: $entity->getMicrochipNumber(),
            tattooNumber: $entity->getTattooNumber(),
            passportNumber: $entity->getPassportNumber(),
            registryType: $entity->getRegistryType()->value,
            registryNumber: $entity->getRegistryNumber(),
            sireNumber: $entity->getSireNumber(),
        );

        $lifeCycle = new LifeCycleDto(
            lifeStatus: $entity->getLifeStatus()->value,
            deceasedAt: $entity->getDeceasedAt()?->format(\DateTimeInterface::ATOM),
            missingSince: $entity->getMissingSince()?->format(\DateTimeInterface::ATOM),
        );

        $transfer = new TransferDto(
            transferStatus: $entity->getTransferStatus()->value,
            soldAt: $entity->getSoldAt()?->format(\DateTimeInterface::ATOM),
            givenAt: $entity->getGivenAt()?->format(\DateTimeInterface::ATOM),
        );

        $auxiliaryContact    = null;
        $hasAuxiliaryContact = null !== $entity->getAuxiliaryContactFirstName()
            && null !== $entity->getAuxiliaryContactLastName()
            && null !== $entity->getAuxiliaryContactPhoneNumber();

        if ($hasAuxiliaryContact) {
            \assert(null !== $entity->getAuxiliaryContactFirstName());
            \assert(null !== $entity->getAuxiliaryContactLastName());
            \assert(null !== $entity->getAuxiliaryContactPhoneNumber());

            $auxiliaryContact = new AuxiliaryContactDto(
                firstName: $entity->getAuxiliaryContactFirstName(),
                lastName: $entity->getAuxiliaryContactLastName(),
                phoneNumber: $entity->getAuxiliaryContactPhoneNumber(),
            );
        }

        $ownerships = [];
        foreach ($entity->getOwnerships() as $ownershipEntity) {
            $ownerships[] = new OwnershipDto(
                clientId: $ownershipEntity->getClientId()->toString(),
                role: $ownershipEntity->getRole()->value,
                status: $ownershipEntity->getStatus()->value,
                startedAt: $ownershipEntity->getStartedAt()->format(\DateTimeInterface::ATOM),
                endedAt: $ownershipEntity->getEndedAt()?->format(\DateTimeInterface::ATOM),
            );
        }

        return new AnimalView(
            id: $entity->getId()->toString(),
            clinicId: $entity->getClinicId()->toString(),
            name: $entity->getName(),
            species: $entity->getSpecies()->value,
            sex: $entity->getSex()->value,
            reproductiveStatus: $entity->getReproductiveStatus()->value,
            isMixedBreed: $entity->isMixedBreed(),
            breedName: $entity->getBreedName(),
            birthDate: $entity->getBirthDate()?->format('Y-m-d'),
            color: $entity->getColor(),
            photoUrl: $entity->getPhotoUrl(),
            identification: $identification,
            lifeCycle: $lifeCycle,
            transfer: $transfer,
            auxiliaryContact: $auxiliaryContact,
            status: $entity->getStatus()->value,
            ownerships: $ownerships,
            createdAt: $entity->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $entity->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    private function mapToAnimalListItemView(AnimalEntity $entity): AnimalListItemView
    {
        // Find primary owner
        $primaryOwnerClientId = null;
        foreach ($entity->getOwnerships() as $ownership) {
            $isPrimary = OwnershipRole::PRIMARY === $ownership->getRole();
            $isActive  = OwnershipStatus::ACTIVE === $ownership->getStatus();

            if ($isPrimary && $isActive) {
                $primaryOwnerClientId = $ownership->getClientId()->toString();
                break;
            }
        }

        return new AnimalListItemView(
            id: $entity->getId()->toString(),
            name: $entity->getName(),
            species: $entity->getSpecies()->value,
            sex: $entity->getSex()->value,
            breedName: $entity->getBreedName(),
            birthDate: $entity->getBirthDate()?->format('Y-m-d'),
            color: $entity->getColor(),
            microchipNumber: $entity->getMicrochipNumber(),
            status: $entity->getStatus()->value,
            lifeStatus: $entity->getLifeStatus()->value,
            primaryOwnerClientId: $primaryOwnerClientId,
            createdAt: $entity->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
