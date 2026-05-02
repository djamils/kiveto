<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Search;

use App\Context\Animal\Domain\Event\AnimalCreated;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class OnAnimalCreated
{
    public function __construct(
        private AnimalSearchEntryWriterInterface $writer,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(AnimalCreated $event): void
    {
        $payload = $event->payload();
        \assert(\is_string($payload['animalId']));
        \assert(\is_string($payload['clinicId']));
        \assert(\is_string($payload['name']));

        $conn         = $this->entityManager->getConnection();
        $animalBinary = Uuid::fromString($payload['animalId'])->toBinary();

        $row = $conn->fetchAssociative(
            'SELECT species, breed_name, microchip_number FROM animal__animals WHERE id = :id',
            ['id' => $animalBinary],
        );

        \assert(false !== $row);

        $ownerName  = null;
        $ownerPhone = null;

        if (isset($payload['primaryOwnerClientId']) && \is_string($payload['primaryOwnerClientId'])) {
            $ownerBinary = Uuid::fromString($payload['primaryOwnerClientId'])->toBinary();
            $ownerRow    = $conn->fetchAssociative(
                "SELECT CONCAT(first_name, ' ', last_name) AS name FROM client__clients WHERE id = :id",
                ['id' => $ownerBinary],
            );

            if (false !== $ownerRow && isset($ownerRow['name']) && \is_string($ownerRow['name'])) {
                $ownerName = $ownerRow['name'];
            }

            $phoneRow = $conn->fetchAssociative(
                "SELECT value FROM client__contact_methods WHERE client_id = :id AND type = 'phone' AND is_primary = 1 LIMIT 1",
                ['id' => $ownerBinary],
            );

            if (false !== $phoneRow && isset($phoneRow['value']) && \is_string($phoneRow['value'])) {
                $ownerPhone = $phoneRow['value'];
            }
        }

        $chipNumber = isset($row['microchip_number']) && \is_string($row['microchip_number'])
            ? $row['microchip_number']
            : null;

        $breedName = isset($row['breed_name']) && \is_string($row['breed_name'])
            ? $row['breed_name']
            : null;

        \assert(\is_string($row['species']));

        $this->writer->upsert(new AnimalSearchEntryData(
            animalId: $payload['animalId'],
            clinicId: $payload['clinicId'],
            animalName: $payload['name'],
            species: $row['species'],
            breedName: $breedName,
            chipNumber: $chipNumber,
            ownerName: $ownerName,
            ownerPhone: $ownerPhone,
            primaryOwnerClientId: isset($payload['primaryOwnerClientId']) && \is_string($payload['primaryOwnerClientId'])
                ? $payload['primaryOwnerClientId']
                : null,
            status: 'active',
        ));
    }
}
