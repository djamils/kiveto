<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Messaging\Consumer;

use App\Context\Patient\Domain\Event\PatientReconciledIntoIntegrationEvent;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'messenger.bus.integration_event')]
final readonly class PatientReconciledIntoHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function __invoke(PatientReconciledIntoIntegrationEvent $event): void
    {
        $sourceBinary = Uuid::fromString($event->sourcePatientId)->toBinary();
        $targetBinary = Uuid::fromString($event->targetPatientId)->toBinary();

        // Bulk-update all consultations referencing the merged (source) patient
        // to point to the surviving (target) patient.
        $this->connection->executeStatement(
            'UPDATE consultation__consultations SET patient_id = :targetId WHERE patient_id = :sourceId',
            [
                'targetId' => $targetBinary,
                'sourceId' => $sourceBinary,
            ],
        );
    }
}
