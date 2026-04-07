<?php

declare(strict_types=1);

namespace App\Client\Infrastructure\Persistence\Doctrine\Repository;

use App\Client\Application\Port\ClientReadRepositoryInterface;
use App\Client\Application\Query\GetClientById\ClientView;
use App\Client\Application\Query\GetClientById\ContactMethodDto;
use App\Client\Application\Query\GetClientById\PostalAddressDto;
use App\Client\Application\Query\SearchClients\ClientListItemView;
use App\Client\Application\Query\SearchClients\SearchClientsCriteria;
use App\Client\Domain\ValueObject\ClientId;
use App\Client\Infrastructure\Persistence\Doctrine\Entity\ClientEntity;
use App\Client\Infrastructure\Persistence\Doctrine\Entity\ContactMethodEntity;
use App\Clinic\Domain\ValueObject\ClinicId;
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

        // Build WHERE clause
        $where  = ['c.clinic_id = :clinicId'];
        $params = ['clinicId' => Uuid::fromString($clinicId->toString())->toBinary()];

        if (null !== $criteria->status) {
            $where[]          = 'c.status = :status';
            $params['status'] = $criteria->status;
        }

        if (null !== $criteria->searchTerm && '' !== trim($criteria->searchTerm)) {
            $where[]          = '(c.first_name LIKE :search OR c.last_name LIKE :search)';
            $params['search'] = '%' . $criteria->searchTerm . '%';
        }

        $whereClause = implode(' AND ', $where);

        // Count total results
        $countSql = "SELECT COUNT(*) FROM client__clients c WHERE {$whereClause}";
        $count    = $conn->fetchOne($countSql, $params);
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
            ORDER BY c.last_name ASC, c.first_name ASC
            LIMIT {$criteria->limit} OFFSET {$criteria->offset()}
        ";

        $results = $conn->fetchAllAssociative($sql, $params);

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
                );
            },
            $results
        );

        return [
            'items' => $items,
            'total' => $total,
        ];
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
