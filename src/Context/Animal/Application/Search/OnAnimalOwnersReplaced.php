<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Search;

use App\Context\Animal\Domain\Event\AnimalOwnersReplaced;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class OnAnimalOwnersReplaced
{
    public function __construct(
        private AnimalSearchEntryWriterInterface $writer,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(AnimalOwnersReplaced $event): void
    {
        $payload = $event->payload();
        \assert(\is_string($payload['animalId']));
        \assert(\is_string($payload['clinicId']));
        \assert(\is_string($payload['primaryOwnerClientId']));

        $primaryOwnerId = $payload['primaryOwnerClientId'];
        $conn           = $this->entityManager->getConnection();
        $ownerBinary    = Uuid::fromString($primaryOwnerId)->toBinary();

        $ownerRow = $conn->fetchAssociative(
            "SELECT CONCAT(first_name, ' ', last_name) AS name FROM client__clients WHERE id = :id",
            ['id' => $ownerBinary],
        );

        $ownerName = (false !== $ownerRow && isset($ownerRow['name']) && \is_string($ownerRow['name']))
            ? $ownerRow['name']
            : null;

        $phoneRow = $conn->fetchAssociative(
            "SELECT value FROM client__contact_methods WHERE client_id = :id AND type = 'phone' AND is_primary = 1 LIMIT 1",
            ['id' => $ownerBinary],
        );

        $ownerPhone = (false !== $phoneRow && isset($phoneRow['value']) && \is_string($phoneRow['value']))
            ? $phoneRow['value']
            : null;

        $this->writer->updateOwner(
            $payload['animalId'],
            $payload['clinicId'],
            $primaryOwnerId,
            $ownerName,
            $ownerPhone,
        );
    }
}
