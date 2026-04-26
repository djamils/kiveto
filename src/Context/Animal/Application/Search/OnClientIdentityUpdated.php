<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Search;

use App\Context\Client\Domain\Event\ClientIdentityUpdated;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class OnClientIdentityUpdated
{
    public function __construct(
        private AnimalSearchEntryWriterInterface $writer,
    ) {
    }

    public function __invoke(ClientIdentityUpdated $event): void
    {
        $payload = $event->payload();
        \assert(\is_string($payload['clientId']));
        \assert(\is_string($payload['clinicId']));
        \assert(\is_string($payload['firstName']));
        \assert(\is_string($payload['lastName']));

        $newName = $payload['firstName'] . ' ' . $payload['lastName'];

        $this->writer->updateOwnerName($payload['clientId'], $payload['clinicId'], $newName);
    }
}
