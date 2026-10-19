<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Search;

use App\Context\Client\Domain\Event\ClientTaxProfileUpdated;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class OnClientTaxProfileUpdated
{
    public function __construct(
        private AnimalSearchEntryWriterInterface $writer,
    ) {
    }

    public function __invoke(ClientTaxProfileUpdated $event): void
    {
        $payload = $event->payload();
        \assert(\is_string($payload['clientId']));
        \assert(\is_string($payload['clinicId']));
        \assert(\is_string($payload['firstName']));
        \assert(\is_string($payload['lastName']));

        // A structure is labelled by its legal name, a person by their name.
        $legalName = $payload['legalName'];
        $ownerName = \is_string($legalName) ? $legalName : $payload['firstName'] . ' ' . $payload['lastName'];

        $this->writer->updateOwnerName($payload['clientId'], $payload['clinicId'], $ownerName);
    }
}
