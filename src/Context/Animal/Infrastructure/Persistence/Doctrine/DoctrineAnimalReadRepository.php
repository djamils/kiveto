<?php

declare(strict_types=1);

namespace App\Context\Animal\Infrastructure\Persistence\Doctrine;

use App\Context\Animal\Application\Port\AnimalReadRepositoryInterface;
use App\Context\Animal\Application\Query\FindByMicrochip\AnimalMicrochipView;
use App\Context\Animal\Application\Query\GetAnimalById\AnimalView;
use App\Context\Animal\Application\Query\GetAnimalById\AuxiliaryContactDto;
use App\Context\Animal\Application\Query\GetAnimalById\IdentificationDto;
use App\Context\Animal\Application\Query\GetAnimalById\LifeCycleDto;
use App\Context\Animal\Application\Query\GetAnimalById\OwnershipDto;
use App\Context\Animal\Application\Query\GetAnimalById\TransferDto;
use App\Context\Animal\Application\Query\ListAnimalSummariesPerClientIds\AnimalSummary;
use App\Context\Animal\Application\Query\ListLatestPreventiveCare\LatestPreventiveCareView;
use App\Context\Animal\Application\Query\ListPortalPets\PortalPetView;
use App\Context\Animal\Application\Query\SearchAnimals\AnimalListItemView;
use App\Context\Animal\Application\Query\SearchAnimals\SearchAnimalsCriteria;
use App\Context\Animal\Domain\ValueObject\AnimalId;
use App\Context\Animal\Domain\ValueObject\AnimalStatus;
use App\Context\Animal\Domain\ValueObject\ClinicId;
use App\Context\Animal\Domain\ValueObject\LifeStatus;
use App\Context\Animal\Domain\ValueObject\OwnershipRole;
use App\Context\Animal\Domain\ValueObject\OwnershipStatus;
use App\Context\Animal\Domain\ValueObject\TransferStatus;
use App\Context\Animal\Infrastructure\Persistence\Doctrine\Entity\AnimalEntity;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\ArrayParameterType;
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

    public function findByIds(ClinicId $clinicId, array $animalIds): array
    {
        // The ownerships are fetched with the animals: one query, not one per animal.
        /** @var list<AnimalEntity> $entities */
        $entities = $this->entityManager->createQueryBuilder()
            ->select('a', 'o')
            ->from(AnimalEntity::class, 'a')
            ->leftJoin('a.ownerships', 'o')
            ->where('a.clinicId = :clinicId')
            ->andWhere('a.id IN (:ids)')
            ->setParameter('clinicId', Uuid::fromString($clinicId->toString()), UuidType::NAME)
            ->setParameter('ids', array_map(static fn (string $id): string => Uuid::fromString($id)->toBinary(), $animalIds), ArrayParameterType::BINARY)
            ->getQuery()
            ->getResult()
        ;

        return array_map($this->mapToAnimalView(...), $entities);
    }

    public function search(ClinicId $clinicId, SearchAnimalsCriteria $criteria): array
    {
        if ($criteria->isImpossible()) {
            return ['items' => [], 'total' => 0];
        }

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
        $direction = 'desc' === $criteria->direction ? 'DESC' : 'ASC';
        $column    = match ($criteria->sort) {
            SearchAnimalsCriteria::SORT_SPECIES => 'a.species',
            SearchAnimalsCriteria::SORT_STATUS  => 'a.lifeStatus',
            SearchAnimalsCriteria::SORT_CREATED => 'a.createdAt',
            default                             => 'a.name',
        };

        $qb->setFirstResult($criteria->offset())
            ->setMaxResults($criteria->limit)
            ->orderBy($column, $direction)
            // Stable tie-break so paging never repeats or skips a row.
            ->addOrderBy('a.id', 'ASC')
        ;

        $entities = $qb->getQuery()->getResult();

        /** @phpstan-ignore-next-line argument.type */
        $items = array_values(array_map(fn (AnimalEntity $entity) => $this->mapToAnimalListItemView($entity), $entities)); // phpcs:ignore

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    public function countBy(ClinicId $clinicId, SearchAnimalsCriteria $criteria): int
    {
        if ($criteria->isImpossible()) {
            return 0;
        }

        $clinicUuid = Uuid::fromString($clinicId->toString());
        $qb         = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(DISTINCT a.id)')
            ->from(AnimalEntity::class, 'a')
            ->where('a.clinicId = :clinicId')
            ->setParameter('clinicId', $clinicUuid, UuidType::NAME)
        ;

        $this->applySearchCriteria($qb, $criteria);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findIdsMatching(ClinicId $clinicId, ?string $searchTerm, ?string $species): array
    {
        $sql        = 'SELECT id FROM animal__search_entries WHERE clinic_id = :clinicId';
        $parameters = ['clinicId' => Uuid::fromString($clinicId->toString())->toBinary()];

        if (null !== $species && '' !== $species) {
            $sql .= ' AND species = :species';
            $parameters['species'] = $species;
        }

        if (null !== $searchTerm && '' !== $searchTerm) {
            // The search entry denormalises the animal, its owner and its breed,
            // so one LIKE covers the whole "patient or owner" search box.
            $sql .= ' AND (search_name LIKE :term OR search_owner_name LIKE :term OR breed_name LIKE :term)';
            $parameters['term'] = '%' . $searchTerm . '%';
        }

        $rows = $this->entityManager->getConnection()->fetchAllAssociative($sql, $parameters);

        return array_map(
            static fn (array $row): string => RowAccessor::uuid($row, 'id'),
            $rows,
        );
    }

    public function listAnimalSummariesByPrimaryOwnerClientIds(
        ClinicId $clinicId,
        array $clientIds,
        int $limit,
    ): array {
        if ([] === $clientIds) {
            return [];
        }

        $conn         = $this->entityManager->getConnection();
        $clinicBinary = Uuid::fromString($clinicId->toString())->toBinary();
        $binaryIds    = array_map(
            static fn (string $id) => Uuid::fromString($id)->toBinary(),
            $clientIds,
        );

        $sql = '
            SELECT BIN_TO_UUID(o.client_id) AS clientId,
                   BIN_TO_UUID(a.id) AS animalId,
                   a.name,
                   a.species
            FROM animal__ownerships o
            JOIN animal__animals a ON a.id = o.animal_id
            WHERE a.clinic_id = :clinicId
              AND o.client_id IN (:clientIds)
              AND o.role = :role
              AND o.status = :ownershipStatus
              AND a.status = :animalStatus
            ORDER BY o.client_id, a.name ASC
        ';

        $rows = $conn->fetchAllAssociative(
            $sql,
            [
                'clinicId'        => $clinicBinary,
                'clientIds'       => $binaryIds,
                'role'            => 'primary',
                'ownershipStatus' => 'active',
                'animalStatus'    => 'active',
            ],
            [
                'clientIds' => ArrayParameterType::STRING,
            ],
        );

        $grouped = [];
        foreach ($rows as $row) {
            \assert(\is_string($row['clientId']));
            \assert(\is_string($row['animalId']));
            \assert(\is_string($row['name']));
            \assert(\is_string($row['species']));

            $clientId = $row['clientId'];

            if (!isset($grouped[$clientId])) {
                $grouped[$clientId] = [];
            }

            if (\count($grouped[$clientId]) < $limit) {
                $grouped[$clientId][] = new AnimalSummary(
                    id: $row['animalId'],
                    name: $row['name'],
                    species: $row['species'],
                );
            }
        }

        return $grouped;
    }

    public function findByMicrochip(string $microchipNumber, string $clinicId): ?AnimalMicrochipView
    {
        $clinicUuid = Uuid::fromString($clinicId);
        $repository = $this->entityManager->getRepository(AnimalEntity::class);

        $entity = $repository->findOneBy([
            'microchipNumber' => $microchipNumber,
            'clinicId'        => $clinicUuid,
        ]);

        if (null === $entity) {
            return null;
        }

        return new AnimalMicrochipView(
            animalId: $entity->getId()->toString(),
            clinicId: $entity->getClinicId()->toString(),
            name: $entity->getName(),
        );
    }

    public function findRecentPetOfOwner(
        ClinicId $clinicId,
        string $animalId,
        string $primaryOwnerClientId,
        string $name,
        string $species,
        \DateTimeImmutable $createdSince,
    ): ?string {
        if (!Uuid::isValid($animalId)) {
            return null;
        }

        $id = $this->entityManager->getConnection()->fetchOne(
            'SELECT a.id
             FROM animal__ownerships o
             JOIN animal__animals a ON a.id = o.animal_id
             WHERE a.id = :animalId
               AND a.clinic_id = :clinicId
               AND o.client_id = :clientId
               AND o.role = :role
               AND o.status = :ownershipStatus
               AND a.status = :animalStatus
               AND a.life_status = :alive
               AND a.transfer_status = :transferStatus
               AND a.species = :species
               AND LOWER(TRIM(a.name)) = :name
               AND a.created_at >= :since',
            [
                'animalId'        => Uuid::fromString($animalId)->toBinary(),
                'clinicId'        => Uuid::fromString($clinicId->toString())->toBinary(),
                'clientId'        => Uuid::fromString($primaryOwnerClientId)->toBinary(),
                'role'            => OwnershipRole::PRIMARY->value,
                'ownershipStatus' => OwnershipStatus::ACTIVE->value,
                'animalStatus'    => AnimalStatus::ACTIVE->value,
                'alive'           => LifeStatus::ALIVE->value,
                'transferStatus'  => TransferStatus::NONE->value,
                'species'         => $species,
                'name'            => mb_strtolower(trim($name)),
                'since'           => $createdSince->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            ],
        );

        return \is_string($id) ? Uuid::fromBinary($id)->toRfc4122() : null;
    }

    public function listPortalPets(ClinicId $clinicId, array $clientIds): array
    {
        if ([] === $clientIds) {
            return [];
        }

        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT a.id, o.client_id, a.name, a.species, a.breed_name, a.is_mixed_breed, a.sex,
                    a.reproductive_status, DATE(a.birth_date) AS birth_date, a.life_status, a.microchip_number
             FROM animal__ownerships o
             JOIN animal__animals a ON a.id = o.animal_id
             WHERE a.clinic_id = :clinicId
               AND o.client_id IN (:clientIds)
               AND o.role = :role
               AND o.status = :ownershipStatus
               AND a.status = :animalStatus
               AND a.transfer_status = :transferStatus
             ORDER BY a.life_status = :alive DESC, a.name ASC, a.id ASC',
            [
                'clinicId'        => Uuid::fromString($clinicId->toString())->toBinary(),
                'clientIds'       => $this->binaryIds($clientIds),
                'role'            => OwnershipRole::PRIMARY->value,
                'ownershipStatus' => OwnershipStatus::ACTIVE->value,
                'animalStatus'    => AnimalStatus::ACTIVE->value,
                'transferStatus'  => TransferStatus::NONE->value,
                'alive'           => LifeStatus::ALIVE->value,
            ],
            ['clientIds' => ArrayParameterType::BINARY],
        );

        return array_map(
            static fn (array $row): PortalPetView => new PortalPetView(
                id: RowAccessor::uuid($row, 'id'),
                primaryOwnerClientId: RowAccessor::uuid($row, 'client_id'),
                name: RowAccessor::string($row, 'name'),
                species: RowAccessor::string($row, 'species'),
                breedName: RowAccessor::nullableString($row, 'breed_name'),
                isMixedBreed: 1 === RowAccessor::int($row, 'is_mixed_breed'),
                sex: RowAccessor::string($row, 'sex'),
                reproductiveStatus: RowAccessor::string($row, 'reproductive_status'),
                birthDate: self::nullableDate($row, 'birth_date'),
                lifeStatus: RowAccessor::string($row, 'life_status'),
                microchipNumber: RowAccessor::nullableString($row, 'microchip_number'),
            ),
            $rows,
        );
    }

    public function listLatestPreventiveCare(ClinicId $clinicId, array $animalIds): array
    {
        if ([] === $animalIds) {
            return [];
        }

        // Every record is ranked, so a renewal without a due date hides an older
        // act that had one; callers filter on `nextDueOn` themselves.
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT animal_id, kind, label, administered_on, batch_number, next_due_on
             FROM (
                 SELECT animal_id, kind, label, administered_on, batch_number, next_due_on,
                        ROW_NUMBER() OVER (
                            PARTITION BY animal_id, kind, LOWER(label)
                            ORDER BY administered_on DESC, recorded_at DESC
                        ) AS rank_in_protocol
                 FROM animal__preventive_care_records
                 WHERE clinic_id = :clinicId
                   AND animal_id IN (:animalIds)
             ) acts
             WHERE rank_in_protocol = 1
             ORDER BY animal_id, kind, label',
            [
                'clinicId'  => Uuid::fromString($clinicId->toString())->toBinary(),
                'animalIds' => $this->binaryIds($animalIds),
            ],
            ['animalIds' => ArrayParameterType::BINARY],
        );

        return array_map(
            static fn (array $row): LatestPreventiveCareView => new LatestPreventiveCareView(
                animalId: RowAccessor::uuid($row, 'animal_id'),
                kind: RowAccessor::string($row, 'kind'),
                label: RowAccessor::string($row, 'label'),
                administeredOn: new \DateTimeImmutable(RowAccessor::string($row, 'administered_on')),
                batchNumber: RowAccessor::nullableString($row, 'batch_number'),
                nextDueOn: self::nullableDate($row, 'next_due_on'),
            ),
            $rows,
        );
    }

    /**
     * @param list<string> $ids UUID strings
     *
     * @return list<string>
     */
    private function binaryIds(array $ids): array
    {
        return array_map(static fn (string $id): string => Uuid::fromString($id)->toBinary(), $ids);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function nullableDate(array $row, string $key): ?\DateTimeImmutable
    {
        $value = RowAccessor::nullableString($row, $key);

        return null === $value ? null : new \DateTimeImmutable($value);
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

        if ([] !== $criteria->speciesList) {
            $qb->andWhere('a.species IN (:speciesList)')
                ->setParameter('speciesList', $criteria->speciesList)
            ;
        }

        if (null !== $criteria->lifeStatus) {
            $qb->andWhere('a.lifeStatus = :lifeStatus')
                ->setParameter('lifeStatus', $criteria->lifeStatus)
            ;
        }

        if ([] !== $criteria->lifeStatuses) {
            $qb->andWhere('a.lifeStatus IN (:lifeStatuses)')
                ->setParameter('lifeStatuses', $criteria->lifeStatuses)
            ;
        }

        if (null !== $criteria->restrictToIds) {
            // Free text is resolved to identifiers by the caller, so the search
            // can span the owner name without this context joining to Client.
            // Binary, explicitly typed: an array of Uuid objects is not
            // converted for an IN clause and would silently match nothing.
            $qb->andWhere('a.id IN (:restrictToIds)')
                ->setParameter(
                    'restrictToIds',
                    array_map(
                        static fn (string $id): string => Uuid::fromString($id)->toBinary(),
                        $criteria->restrictToIds,
                    ),
                    ArrayParameterType::BINARY,
                )
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
            registryReference: $entity->getRegistryReference(),
        );

        $lifeCycle = new LifeCycleDto(
            lifeStatus: $entity->getLifeStatus()->value,
            deceasedAt: $entity->getDeceasedAt()?->format(\DateTimeInterface::ATOM),
            missingSince: $entity->getMissingSince()?->format(\DateTimeInterface::ATOM),
            deathCause: $entity->getDeathCause()?->value,
            bodyDisposal: $entity->getBodyDisposal()?->value,
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
            insurerName: $entity->getInsurerName(),
            insurancePolicyNumber: $entity->getInsurancePolicyNumber(),
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
