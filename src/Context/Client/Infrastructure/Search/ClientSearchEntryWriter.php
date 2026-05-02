<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Search;

use App\Context\Client\Application\Search\ClientSearchEntryData;
use App\Context\Client\Application\Search\ClientSearchEntryWriterInterface;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Entity\SearchEntryEntity;
use App\Shared\Search\Normalization\SearchTermNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class ClientSearchEntryWriter implements ClientSearchEntryWriterInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SearchTermNormalizer $normalizer,
    ) {
    }

    public function upsert(ClientSearchEntryData $data): void
    {
        $id     = Uuid::fromString($data->clientId);
        $entity = $this->entityManager->find(SearchEntryEntity::class, $id);
        $name   = $data->firstName . ' ' . $data->lastName;

        $normalizedPhone = null !== $data->phone
            ? $this->normalizer->normalizePhone($data->phone)
            : null;

        if (null === $entity) {
            $entity = new SearchEntryEntity(
                id: $id,
                clinicId: Uuid::fromString($data->clinicId),
                firstName: $data->firstName,
                lastName: $data->lastName,
                searchName: $this->normalizer->normalizeText($name),
                searchPhone: $normalizedPhone,
                primaryEmail: $data->email,
                status: $data->status,
                updatedAt: new \DateTimeImmutable(),
            );

            $this->entityManager->persist($entity);
        } else {
            $entity->setFirstName($data->firstName);
            $entity->setLastName($data->lastName);
            $entity->setSearchName($this->normalizer->normalizeText($name));
            $entity->setSearchPhone($normalizedPhone);
            $entity->setPrimaryEmail($data->email);
            $entity->setStatus($data->status);
            $entity->setUpdatedAt(new \DateTimeImmutable());
        }

        $this->entityManager->flush();
    }

    public function updateName(
        string $clientId,
        string $clinicId,
        string $firstName,
        string $lastName,
    ): void {
        $entity = $this->loadOrNull($clientId, $clinicId);

        if (null === $entity) {
            return;
        }

        $name = $firstName . ' ' . $lastName;
        $entity->setFirstName($firstName);
        $entity->setLastName($lastName);
        $entity->setSearchName($this->normalizer->normalizeText($name));
        $entity->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }

    public function updateContactMethods(
        string $clientId,
        string $clinicId,
        ?string $phone,
        ?string $email,
    ): void {
        $entity = $this->loadOrNull($clientId, $clinicId);

        if (null === $entity) {
            return;
        }

        $entity->setSearchPhone(null !== $phone ? $this->normalizer->normalizePhone($phone) : null);
        $entity->setPrimaryEmail($email);
        $entity->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }

    public function markArchived(string $clientId, string $clinicId): void
    {
        $entity = $this->loadOrNull($clientId, $clinicId);

        if (null === $entity) {
            return;
        }

        $entity->setStatus('archived');
        $entity->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }

    public function markActive(string $clientId, string $clinicId): void
    {
        $entity = $this->loadOrNull($clientId, $clinicId);

        if (null === $entity) {
            return;
        }

        $entity->setStatus('active');
        $entity->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }

    private function loadOrNull(string $clientId, string $clinicId): ?SearchEntryEntity
    {
        $id     = Uuid::fromString($clientId);
        $entity = $this->entityManager->find(SearchEntryEntity::class, $id);

        if (null === $entity) {
            return null;
        }

        if ($entity->getClinicId()->toString() !== $clinicId) {
            return null;
        }

        return $entity;
    }
}
