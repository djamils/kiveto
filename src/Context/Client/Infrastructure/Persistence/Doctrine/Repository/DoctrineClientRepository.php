<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\Exception\ClientNotFoundException;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\Repository\ConsentHistoryRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Entity\ClientEntity;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Entity\ContactMethodEntity;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Mapper\ClientMapper;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineClientRepository implements ClientRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private ClientMapper $mapper,
        private ConsentHistoryRepositoryInterface $consentHistory,
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
            $existingClient->setCivility($clientEntity->getCivility());
            $existingClient->setTaxStatus($clientEntity->getTaxStatus());
            $existingClient->setLegalName($clientEntity->getLegalName());
            $existingClient->setSiren($clientEntity->getSiren());
            $existingClient->setVatNumber($clientEntity->getVatNumber());
            $existingClient->setTaxCompanyCheckOutcome($clientEntity->getTaxCompanyCheckOutcome());
            $existingClient->setTaxCompanyRegistryName($clientEntity->getTaxCompanyRegistryName());
            $existingClient->setTaxCompanyCheckedAt($clientEntity->getTaxCompanyCheckedAt());
            $existingClient->setTaxVatCheckOutcome($clientEntity->getTaxVatCheckOutcome());
            $existingClient->setTaxVatCheckedAt($clientEntity->getTaxVatCheckedAt());
            $existingClient->setTaxVerificationRequestedAt($clientEntity->getTaxVerificationRequestedAt());
            $existingClient->setStatus($clientEntity->getStatus());
            $existingClient->setPostalAddress($clientEntity->getPostalAddress());
            $existingClient->setAuthorizedPerson($clientEntity->getAuthorizedPerson());
            $existingClient->setCommunicationPreferences($clientEntity->getCommunicationPreferences());
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

        // In the caller's transaction: the history and the consents it explains are written together.
        $this->consentHistory->append($client->pullConsentHistory());
    }

    public function get(ClinicId $clinicId, ClientId $clientId): Client
    {
        $client = $this->find($clinicId, $clientId);

        if (null === $client) {
            throw new ClientNotFoundException($clientId->toString());
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

        return $entity instanceof ClientEntity ? $this->toDomain($entity) : null;
    }

    public function getForUpdate(ClinicId $clinicId, ClientId $clientId): Client
    {
        // Refreshed: an entity already loaded by a plain read of this transaction is read again, current.
        $entity = $this->em->createQueryBuilder()
            ->select('c')
            ->from(ClientEntity::class, 'c')
            ->where('c.id = :id')
            ->andWhere('c.clinicId = :clinicId')
            ->setParameter('id', Uuid::fromString($clientId->toString()), UuidType::NAME)
            ->setParameter('clinicId', Uuid::fromString($clinicId->toString()), UuidType::NAME)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult()
        ;

        if (!$entity instanceof ClientEntity) {
            throw new ClientNotFoundException($clientId->toString());
        }

        return $this->toDomain($entity);
    }

    private function toDomain(ClientEntity $entity): Client
    {
        $contactMethodEntities = $this->em->getRepository(ContactMethodEntity::class)->findBy(
            ['clientId' => $entity->getId()],
        );

        return $this->mapper->toDomain($entity, $contactMethodEntities);
    }
}
