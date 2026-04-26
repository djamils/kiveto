<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Search;

use App\Context\Animal\Domain\Event\AnimalArchived;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class OnAnimalArchived
{
    public function __construct(
        private AnimalSearchEntryWriterInterface $writer,
    ) {
    }

    public function __invoke(AnimalArchived $event): void
    {
        $payload = $event->payload();
        \assert(\is_string($payload['animalId']));
        \assert(\is_string($payload['clinicId']));

        $this->writer->markArchived($payload['animalId'], $payload['clinicId']);
    }
}
