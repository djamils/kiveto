<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Client\Application\Port\ClientReadRepositoryInterface;
use App\Context\Client\Application\Query\GetClientById\ClientView;
use App\Context\Client\Application\Query\GetClientById\ContactMethodDto;
use App\Context\Client\Application\Query\GetClientById\PostalAddressDto;
use App\Context\Client\Application\Query\SearchClients\ClientListItemView;
use App\Context\Client\Application\Query\SearchClients\SearchClientsCriteria;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Entity\ClientEntity;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Entity\ContactMethodEntity;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineClientReadRepository implements ClientReadRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function findById(ClinicId $clinicId, ClientId $clientId): ?ClientView
    {
        $clientUuid = Uuid::fromString($clientId->toString());
        $clinicUuid = Uuid::fromString($clinicId->toString());

        $entity = $this->em->getRepository(ClientEntity::class)->findOneBy([
            'id'       => $clientUuid,
            'clinicId' => $clinicUuid,
        ]);

        if (null === $entity) {
            return null;
        }

        $contactMethodEntities = $this->em->getRepository(ContactMethodEntity::class)->findBy(
            ['clientId' => $clientUuid],
        );

        $contactMethods = array_map(
            static fn (ContactMethodEntity $cme): ContactMethodDto => new ContactMethodDto(
                type: $cme->getType()->value,
                label: $cme->getLabel()->value,
                value: $cme->getValue(),
                isPrimary: $cme->isPrimary(),
            ),
            $contactMethodEntities,
        );

        $postalAddress = $this->buildPostalAddressDto($entity);

        return new ClientView(
            id: $entity->getId()->toString(),
            clinicId: $entity->getClinicId()->toString(),
            firstName: $entity->getFirstName(),
            lastName: $entity->getLastName(),
            status: $entity->getStatus()->value,
            contactMethods: $contactMethods,
            postalAddress: $postalAddress,
            createdAt: $entity->getCreatedAt()->format('c'),
            updatedAt: $entity->getUpdatedAt()->format('c'),
        );
    }

    public function search(ClinicId $clinicId, SearchClientsCriteria $criteria): array
    {
        $conn = $this->em->getConnection();

        [$whereClause, $params, $types] = $this->buildSearchWhereClause($clinicId, $criteria);

        // Count total results
        $countSql = "SELECT COUNT(*) FROM client__clients c WHERE {$whereClause}";
        $count    = $conn->fetchOne($countSql, $params, $types);
        \assert(is_numeric($count));
        $total = (int) $count;

        if (0 === $total) {
            return ['items' => [], 'total' => 0];
        }

        // Main query with correlated subqueries for primary contact methods
        $sql = "
            SELECT 
                BIN_TO_UUID(c.id) as id,
                c.first_name as firstName,
                c.last_name as lastName,
                c.status,
                c.created_at as createdAt,
                c.postal_address_city as city,
                (
                    SELECT cm.value 
                    FROM client__contact_methods cm
                    WHERE cm.client_id = c.id AND cm.type = 'phone'
                    ORDER BY cm.is_primary DESC
                    LIMIT 1
                ) as primaryPhone,
                (
                    SELECT cm.value 
                    FROM client__contact_methods cm
                    WHERE cm.client_id = c.id AND cm.type = 'email'
                    ORDER BY cm.is_primary DESC
                    LIMIT 1
                ) as primaryEmail
            FROM client__clients c
            WHERE {$whereClause}
            ORDER BY {$this->buildSearchOrderBy($criteria)}
            LIMIT {$criteria->limit} OFFSET {$criteria->offset()}
        ";

        $results = $conn->fetchAllAssociative($sql, $params, $types);

        $items = array_map(
            static function (array $row): ClientListItemView {
                \assert(\is_string($row['id']));
                \assert(\is_string($row['firstName']));
                \assert(\is_string($row['lastName']));
                \assert(\is_string($row['status']));
                \assert(\is_string($row['createdAt']));

                return new ClientListItemView(
                    id: $row['id'],
                    firstName: $row['firstName'],
                    lastName: $row['lastName'],
                    status: $row['status'],
                    primaryPhone: \is_string($row['primaryPhone'] ?? null) ? $row['primaryPhone'] : null,
                    primaryEmail: \is_string($row['primaryEmail'] ?? null) ? $row['primaryEmail'] : null,
                    createdAt: (new \DateTimeImmutable($row['createdAt']))->format('c'),
                    city: \is_string($row['city'] ?? null) && '' !== $row['city'] ? $row['city'] : null,
                );
            },
            $results
        );

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    public function emailExistsInClinic(ClinicId $clinicId, string $email): bool
    {
        $sql = <<<'SQL'
            SELECT 1
            FROM client__clients c
            INNER JOIN client__contact_methods cm ON cm.client_id = c.id
            WHERE c.clinic_id = :clinicId
              AND cm.type = 'email'
              AND LOWER(cm.value) = LOWER(:email)
            LIMIT 1
        SQL;

        $result = $this->em->getConnection()->fetchOne($sql, [
            'clinicId' => Uuid::fromString($clinicId->toString())->toBinary(),
            'email'    => $email,
        ]);

        return false !== $result;
    }

    public function countBy(ClinicId $clinicId, SearchClientsCriteria $criteria): int
    {
        $conn = $this->em->getConnection();

        [$whereClause, $params, $types] = $this->buildSearchWhereClause($clinicId, $criteria);

        $sql   = "SELECT COUNT(*) FROM client__clients c WHERE {$whereClause}";
        $count = $conn->fetchOne($sql, $params, $types);
        \assert(is_numeric($count));

        return (int) $count;
    }

    public function listCities(ClinicId $clinicId): array
    {
        $rows = $this->em->getConnection()->fetchFirstColumn(
            "SELECT DISTINCT postal_address_city
             FROM client__clients
             WHERE clinic_id = :clinicId AND postal_address_city IS NOT NULL AND postal_address_city <> ''
             ORDER BY postal_address_city ASC",
            ['clinicId' => Uuid::fromString($clinicId->toString())->toBinary()],
        );

        return array_values(array_filter(
            array_map(static fn (mixed $city): string => \is_string($city) ? $city : '', $rows),
            static fn (string $city): bool => '' !== $city,
        ));
    }

    public function findFullNamesByIds(ClinicId $clinicId, array $clientIds): array
    {
        if ([] === $clientIds) {
            return [];
        }

        $conn         = $this->em->getConnection();
        $clinicBinary = Uuid::fromString($clinicId->toString())->toBinary();
        $binaryIds    = array_map(
            static fn (string $id) => Uuid::fromString($id)->toBinary(),
            $clientIds,
        );

        $sql = '
            SELECT BIN_TO_UUID(c.id) AS id, c.first_name AS firstName, c.last_name AS lastName
            FROM client__clients c
            WHERE c.clinic_id = :clinicId
              AND c.id IN (:clientIds)
        ';

        $rows = $conn->fetchAllAssociative(
            $sql,
            [
                'clinicId'  => $clinicBinary,
                'clientIds' => $binaryIds,
            ],
            [
                'clientIds' => ArrayParameterType::STRING,
            ],
        );

        $result = [];
        foreach ($rows as $row) {
            \assert(\is_string($row['id']));
            \assert(\is_string($row['firstName']));
            \assert(\is_string($row['lastName']));
            $result[$row['id']] = trim($row['firstName'] . ' ' . $row['lastName']);
        }

        return $result;
    }

    /**
     * Shared WHERE clause builder used by both `search()` and `countBy()`
     * so that badge counts never drift from the filtered list.
     *
     * @return array{0: string, 1: array<string, string>}
     */
    /**
     * @return array{0: string, 1: array<string, mixed>, 2: array<string, ArrayParameterType>}
     */
    private function buildSearchWhereClause(ClinicId $clinicId, SearchClientsCriteria $criteria): array
    {
        $where  = ['c.clinic_id = :clinicId'];
        $params = ['clinicId' => Uuid::fromString($clinicId->toString())->toBinary()];
        /** @var array<string, ArrayParameterType> $types */
        $types = [];

        if (null !== $criteria->status) {
            $where[]          = 'c.status = :status';
            $params['status'] = $criteria->status;
        }

        if ([] !== $criteria->statuses) {
            $where[]            = 'c.status IN (:statuses)';
            $params['statuses'] = $criteria->statuses;
            $types['statuses']  = ArrayParameterType::STRING;
        }

        if ([] !== $criteria->cities) {
            $where[]          = 'c.postal_address_city IN (:cities)';
            $params['cities'] = $criteria->cities;
            $types['cities']  = ArrayParameterType::STRING;
        }

        if (null !== $criteria->searchTerm && '' !== trim($criteria->searchTerm)) {
            // The directory offers one box for the whole client card, so the
            // term has to reach the contact methods as well as the identity.
            $where[] = '('
                . 'c.first_name LIKE :search OR c.last_name LIKE :search'
                . ' OR c.postal_address_city LIKE :search'
                . ' OR EXISTS ('
                . 'SELECT 1 FROM client__contact_methods cm'
                . ' WHERE cm.client_id = c.id AND cm.value LIKE :search'
                . ')'
                . ')';
            $params['search'] = '%' . $criteria->searchTerm . '%';
        }

        return [implode(' AND ', $where), $params, $types];
    }

    /**
     * Whitelisted ORDER BY, with the name as a stable tie-break.
     */
    private function buildSearchOrderBy(SearchClientsCriteria $criteria): string
    {
        $direction = 'desc' === $criteria->direction ? 'DESC' : 'ASC';

        $column = match ($criteria->sort) {
            SearchClientsCriteria::SORT_CITY    => 'c.postal_address_city',
            SearchClientsCriteria::SORT_CREATED => 'c.created_at',
            default                             => 'c.last_name',
        };

        return $column . ' ' . $direction . ', c.last_name ASC, c.first_name ASC';
    }

    private function buildPostalAddressDto(ClientEntity $entity): ?PostalAddressDto
    {
        $embeddable = $entity->getPostalAddress();

        $isEmpty    = $embeddable->isEmpty();
        $hasStreet  = null !== $embeddable->streetLine1;
        $hasCity    = null !== $embeddable->city;
        $hasCountry = null !== $embeddable->countryCode;

        if (!$isEmpty && $hasStreet && $hasCity && $hasCountry) {
            \assert(null !== $embeddable->streetLine1);
            \assert(null !== $embeddable->city);
            \assert(null !== $embeddable->countryCode);

            return new PostalAddressDto(
                streetLine1: $embeddable->streetLine1,
                city: $embeddable->city,
                countryCode: $embeddable->countryCode,
                streetLine2: $embeddable->streetLine2,
                postalCode: $embeddable->postalCode,
                region: $embeddable->region,
            );
        }

        return null;
    }
}
