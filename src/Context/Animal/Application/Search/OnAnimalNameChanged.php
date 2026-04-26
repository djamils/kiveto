<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Search;

use App\Context\Animal\Domain\Event\AnimalNameChanged;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class OnAnimalNameChanged
{
    public function __construct(
        private AnimalSearchIndexWriterInterface $writer,
    ) {
    }

    public function __invoke(AnimalNameChanged $event): void
    {
        $payload = $event->payload();
        \assert(\is_string($payload['animalId']));
        \assert(\is_string($payload['clinicId']));
        \assert(\is_string($payload['newName']));

        $this->writer->updateName($payload['animalId'], $payload['clinicId'], $payload['newName']);
    }
}
