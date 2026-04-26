<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\EventSubscriber;

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
        $sourceUuid = Uuid::fromString($event->sourcePatientId)->toBinary();
        $targetUuid = Uuid::fromString($event->targetPatientId)->toBinary();
        $clinicUuid = Uuid::fromString($event->clinicId)->toBinary();

        $this->connection->executeStatement(
            'UPDATE admission__admissions SET patient_id = :targetId WHERE patient_id = :sourceId AND clinic_id = :clinicId',
            [
                'targetId' => $targetUuid,
                'sourceId' => $sourceUuid,
                'clinicId' => $clinicUuid,
            ],
        );
    }
}
