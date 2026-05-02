<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Search;

use App\Context\Client\Domain\Event\ClientArchived;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class OnClientArchived
{
    public function __construct(
        private ClientSearchEntryWriterInterface $writer,
    ) {
    }

    public function __invoke(ClientArchived $event): void
    {
        $payload = $event->payload();
        \assert(\is_string($payload['clientId']));
        \assert(\is_string($payload['clinicId']));

        $this->writer->markArchived($payload['clientId'], $payload['clinicId']);
    }
}
