<?php

declare(strict_types=1);

namespace App\Client\Infrastructure\Persistence\Doctrine\Repository;

use App\Client\Application\Port\ClientRepositoryInterface;
use App\Client\Domain\Client;
use App\Client\Domain\Exception\ClientNotFoundException;
use App\Client\Domain\ValueObject\ClientId;
use App\Client\Infrastructure\Persistence\Doctrine\Entity\ClientEntity;
use App\Client\Infrastructure\Persistence\Doctrine\Entity\ContactMethodEntity;
use App\Client\Infrastructure\Persistence\Doctrine\Mapper\ClientMapper;
use App\Clinic\Domain\ValueObject\ClinicId;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineClientRepository implements ClientRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private ClientMapper $mapper,
    ) {
    }

    public function save(Client $client): void
    {
        $mapped         = $this->mapper->toEntity($client);
        $clientEntity   = $mapped['client'];
        $contactMethods = $mapped['contactMethods'];

        $existingClient = $this->em->find(ClientEntity::class, $clientEntity->getId());

        if (null === $existingClient) {
            $this->em->persist($clientEntity);
        } else {
            $existingClient->setFirstName($clientEntity->getFirstName());
            $existingClient->setLastName($clientEntity->getLastName());
            $existingClient->setStatus($clientEntity->getStatus());
            $existingClient->setUpdatedAt($clientEntity->getUpdatedAt());
        }

        $this->em->getRepository(ContactMethodEntity::class)
            ->createQueryBuilder('cm')
            ->delete()
            ->where('cm.clientId = :clientId')
            ->setParameter('clientId', $clientEntity->getId(), 'uuid')
            ->getQuery()
            ->execute()
        ;

        foreach ($contactMethods as $contactMethodEntity) {
            $this->em->persist($contactMethodEntity);
        }

        $this->em->flush();
    }

    public function get(ClinicId $clinicId, ClientId $clientId): Client
    {
        $client = $this->find($clinicId, $clientId);

        if (null === $client) {
            throw ClientNotFoundException::forId($clientId->toString());
        }

        return $client;
    }

    public function find(ClinicId $clinicId, ClientId $clientId): ?Client
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

        return $this->mapper->toDomain($entity, $contactMethodEntities);
    }

    public function nextId(): ClientId
    {
        return ClientId::fromString(Uuid::v7()->toString());
    }
}
