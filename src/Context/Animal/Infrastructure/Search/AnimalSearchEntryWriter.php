<?php

declare(strict_types=1);

namespace App\Context\Animal\Infrastructure\Search;

use App\Context\Animal\Application\Search\AnimalSearchEntryData;
use App\Context\Animal\Application\Search\AnimalSearchEntryWriterInterface;
use App\Context\Animal\Infrastructure\Persistence\Doctrine\Entity\SearchEntryEntity;
use App\Shared\Search\Normalization\SearchTermNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class AnimalSearchEntryWriter implements AnimalSearchEntryWriterInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SearchTermNormalizer $normalizer,
    ) {
    }

    public function upsert(AnimalSearchEntryData $data): void
    {
        $id     = Uuid::fromString($data->animalId);
        $entity = $this->entityManager->find(SearchEntryEntity::class, $id);

        $chip  = '' !== (string) $data->chipNumber ? $data->chipNumber : null;
        $phone = null !== $data->ownerPhone
            ? $this->normalizer->normalizePhone($data->ownerPhone)
            : null;

        if (null === $entity) {
            $entity = new SearchEntryEntity(
                id: $id,
                clinicId: Uuid::fromString($data->clinicId),
                animalName: $data->animalName,
                searchName: $this->normalizer->normalizeText($data->animalName),
                searchChip: $chip,
                searchPhone: $phone,
                species: $data->species,
                breedName: $data->breedName,
                searchOwnerName: $data->ownerName,
                primaryOwnerClientId: null !== $data->primaryOwnerClientId
                    ? Uuid::fromString($data->primaryOwnerClientId)
                    : null,
                status: $data->status,
                updatedAt: new \DateTimeImmutable(),
            );

            $this->entityManager->persist($entity);
        } else {
            $entity->setAnimalName($data->animalName);
            $entity->setSearchName($this->normalizer->normalizeText($data->animalName));
            $entity->setSearchChip($chip);
            $entity->setSearchPhone($phone);
            $entity->setSearchOwnerName($data->ownerName);
            $entity->setPrimaryOwnerClientId(
                null !== $data->primaryOwnerClientId
                    ? Uuid::fromString($data->primaryOwnerClientId)
                    : null,
            );
            $entity->setStatus($data->status);
            $entity->setUpdatedAt(new \DateTimeImmutable());
        }

        $this->entityManager->flush();
    }

    public function updateName(string $animalId, string $clinicId, string $newName): void
    {
        $entity = $this->loadOrNull($animalId, $clinicId);

        if (null === $entity) {
            return;
        }

        $entity->setAnimalName($newName);
        $entity->setSearchName($this->normalizer->normalizeText($newName));
        $entity->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }

    public function updateChip(string $animalId, string $clinicId, ?string $chip): void
    {
        $entity = $this->loadOrNull($animalId, $clinicId);

        if (null === $entity) {
            return;
        }

        $entity->setSearchChip('' !== (string) $chip ? $chip : null);
        $entity->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }

    public function updateOwner(
        string $animalId,
        string $clinicId,
        ?string $ownerClientId,
        ?string $ownerName,
        ?string $ownerPhone = null,
    ): void {
        $entity = $this->loadOrNull($animalId, $clinicId);

        if (null === $entity) {
            return;
        }

        $entity->setPrimaryOwnerClientId(
            null !== $ownerClientId ? Uuid::fromString($ownerClientId) : null,
        );
        $entity->setSearchOwnerName($ownerName);
        $entity->setSearchPhone(
            null !== $ownerPhone ? $this->normalizer->normalizePhone($ownerPhone) : null,
        );
        $entity->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }

    public function updateOwnerName(string $clientId, string $clinicId, string $newOwnerName): void
    {
        $conn         = $this->entityManager->getConnection();
        $clientBinary = Uuid::fromString($clientId)->toBinary();
        $clinicBinary = Uuid::fromString($clinicId)->toBinary();

        $conn->executeStatement(
            'UPDATE animal__search_entries SET search_owner_name = :name WHERE primary_owner_client_id = :clientId AND clinic_id = :clinicId',
            ['name' => $newOwnerName, 'clientId' => $clientBinary, 'clinicId' => $clinicBinary],
        );
    }

    public function markArchived(string $animalId, string $clinicId): void
    {
        $entity = $this->loadOrNull($animalId, $clinicId);

        if (null === $entity) {
            return;
        }

        $entity->setStatus('archived');
        $entity->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }

    public function delete(string $animalId, string $clinicId): void
    {
        $entity = $this->loadOrNull($animalId, $clinicId);

        if (null === $entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    private function loadOrNull(string $animalId, string $clinicId): ?SearchEntryEntity
    {
        $id     = Uuid::fromString($animalId);
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
