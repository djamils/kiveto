<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\GetClientsNotificationContacts;

use App\Context\Client\Application\Port\ClientNotificationContactReaderInterface;
use App\Context\Client\Application\Query\GetClientNotificationContact\ClientNotificationContact;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetClientsNotificationContactsHandler
{
    public function __construct(
        private ClientNotificationContactReaderInterface $reader,
    ) {
    }

    /**
     * @return array<string, ClientNotificationContact>
     */
    public function __invoke(GetClientsNotificationContacts $query): array
    {
        $clientIds = array_values(array_unique(array_map(
            static fn (string $id): string => ClientId::fromString($id)->toString(),
            $query->clientIds,
        )));

        if ([] === $clientIds) {
            return [];
        }

        return $this->reader->findForClients(ClinicId::fromString($query->clinicId), $clientIds);
    }
}
