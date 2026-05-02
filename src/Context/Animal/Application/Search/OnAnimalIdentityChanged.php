<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Search;

use App\Context\Animal\Domain\Event\AnimalIdentityChanged;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class OnAnimalIdentityChanged
{
    public function __construct(
        private AnimalSearchEntryWriterInterface $writer,
    ) {
    }

    public function __invoke(AnimalIdentityChanged $event): void
    {
        $payload = $event->payload();
        \assert(\is_string($payload['animalId']));
        \assert(\is_string($payload['clinicId']));

        $chip = isset($payload['microchipNumber']) && \is_string($payload['microchipNumber'])
            ? $payload['microchipNumber']
            : null;

        $this->writer->updateChip($payload['animalId'], $payload['clinicId'], $chip);
    }
}
