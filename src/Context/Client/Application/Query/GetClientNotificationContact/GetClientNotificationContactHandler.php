<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\GetClientNotificationContact;

use App\Context\Client\Application\Port\ClientNotificationContactReaderInterface;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetClientNotificationContactHandler
{
    public function __construct(
        private ClientNotificationContactReaderInterface $reader,
    ) {
    }

    public function __invoke(GetClientNotificationContact $query): ?ClientNotificationContact
    {
        $clientId = ClientId::fromString($query->clientId)->toString();

        return $this->reader->findForClients(ClinicId::fromString($query->clinicId), [$clientId])[$clientId] ?? null;
    }
}
