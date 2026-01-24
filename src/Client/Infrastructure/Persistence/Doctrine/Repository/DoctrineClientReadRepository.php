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
use App\Client\Domain\ValueObject\ContactMethodType;
use App\Client\Infrastructure\Persistence\Doctrine\Entity\ClientEntity;
use App\Client\Infrastructure\Persistence\Doctrine\Entity\ContactMethodEntity;
use App\Clinic\Domain\ValueObject\ClinicId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
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
        $qb = $this->em->getRepository(ClientEntity::class)->createQueryBuilder('c');

        $qb->andWhere('c.clinicId = :clinicId')
            ->setParameter('clinicId', Uuid::fromString($clinicId->toString()), UuidType::NAME)
        ;

        if (null !== $criteria->status) {
            $qb->andWhere('c.status = :status')
                ->setParameter('status', $criteria->status)
            ;
        }

        if (null !== $criteria->searchTerm && '' !== trim($criteria->searchTerm)) {
            $qb->andWhere('c.firstName LIKE :search OR c.lastName LIKE :search')
                ->setParameter('search', '%' . $criteria->searchTerm . '%')
            ;
        }

        $countQb = clone $qb;
        $total   = (int) $countQb->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();

        $qb->orderBy('c.lastName', 'ASC')
            ->addOrderBy('c.firstName', 'ASC')
            ->setFirstResult($criteria->offset())
            ->setMaxResults($criteria->limit)
        ;

        /** @var list<ClientEntity> $entities */
        $entities = $qb->getQuery()->getResult();

        $clientIds = array_map(static fn (ClientEntity $e): Uuid => $e->getId(), $entities);

        $contactMethods = [];

        if ([] !== $clientIds) {
            $cmQb = $this->em->getRepository(ContactMethodEntity::class)->createQueryBuilder('cm');
            $cmQb->andWhere('cm.clientId IN (:clientIds)')
                ->setParameter('clientIds', $clientIds)
            ;

            /** @var list<ContactMethodEntity> $cmEntities */
            $cmEntities = $cmQb->getQuery()->getResult();

            foreach ($cmEntities as $cme) {
                $clientIdStr                    = $cme->getClientId()->toString();
                $contactMethods[$clientIdStr][] = $cme;
            }
        }

        $items = array_map(
            function (ClientEntity $entity) use ($contactMethods): ClientListItemView {
                $clientIdStr = $entity->getId()->toString();
                $cms         = $contactMethods[$clientIdStr] ?? [];

                $primaryPhone = null;
                $primaryEmail = null;

                foreach ($cms as $cm) {
                    if (ContactMethodType::PHONE === $cm->getType() && $cm->isPrimary()) {
                        $primaryPhone = $cm->getValue();
                    }

                    if (ContactMethodType::EMAIL === $cm->getType() && $cm->isPrimary()) {
                        $primaryEmail = $cm->getValue();
                    }
                }

                if (null === $primaryPhone) {
                    foreach ($cms as $cm) {
                        if (ContactMethodType::PHONE === $cm->getType()) {
                            $primaryPhone = $cm->getValue();

                            break;
                        }
                    }
                }

                if (null === $primaryEmail) {
                    foreach ($cms as $cm) {
                        if (ContactMethodType::EMAIL === $cm->getType()) {
                            $primaryEmail = $cm->getValue();

                            break;
                        }
                    }
                }

                return new ClientListItemView(
                    id: $entity->getId()->toString(),
                    firstName: $entity->getFirstName(),
                    lastName: $entity->getLastName(),
                    status: $entity->getStatus()->value,
                    primaryPhone: $primaryPhone,
                    primaryEmail: $primaryEmail,
                    createdAt: $entity->getCreatedAt()->format('c'),
                );
            },
            $entities,
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
