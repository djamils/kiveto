<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Search;

use App\Context\Client\Domain\Event\ClientTaxProfileUpdated;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class OnClientTaxProfileUpdated
{
    public function __construct(
        private ClientSearchEntryWriterInterface $writer,
    ) {
    }

    public function __invoke(ClientTaxProfileUpdated $event): void
    {
        $payload = $event->payload();
        \assert(\is_string($payload['clientId']));
        \assert(\is_string($payload['clinicId']));
        \assert(\is_string($payload['firstName']));
        \assert(\is_string($payload['lastName']));
        \assert(null === $payload['legalName'] || \is_string($payload['legalName']));

        $this->writer->updateName(
            $payload['clientId'],
            $payload['clinicId'],
            $payload['firstName'],
            $payload['lastName'],
            $payload['legalName'],
        );
    }
}
