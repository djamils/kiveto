<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\BackfillDocumentPets;

use App\Context\ClientPortal\Domain\Document\PublishedDocumentRepositoryInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class BackfillDocumentPetsHandler
{
    public function __construct(
        private PublishedDocumentRepositoryInterface $documents,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(BackfillDocumentPets $command): void
    {
        foreach ($this->documents->findByPatientForUpdate($command->clinicId, $command->patientId) as $document) {
            if ([] === $document->petIds()) {
                $document->assignPets([$command->animalId], $this->clock->now());
                $this->documents->save($document);
            }
        }
    }
}
