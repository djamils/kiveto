<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\RepointDocumentPatient;

use App\Context\ClientPortal\Application\Port\VisitPatientsInterface;
use App\Context\ClientPortal\Domain\Document\PublishedDocumentRepositoryInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** The target patient's animal (if any) fills the documents without pet. */
#[AsMessageHandler]
final readonly class RepointDocumentPatientHandler
{
    public function __construct(
        private PublishedDocumentRepositoryInterface $documents,
        private VisitPatientsInterface $visits,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RepointDocumentPatient $command): void
    {
        $now      = $this->clock->now();
        $animalId = $this->visits->animalOfPatient($command->clinicId, $command->targetPatientId);

        foreach ($this->documents->findByPatient($command->clinicId, $command->sourcePatientId) as $document) {
            $document->repointPatient($command->targetPatientId, $now);

            if (null !== $animalId && [] === $document->petIds()) {
                $document->assignPets([$animalId], $now);
            }

            $this->documents->save($document);
        }
    }
}
