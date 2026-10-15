<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Messaging\Consumer;

use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\PatientId;
use App\Context\Patient\Domain\Event\PatientReconciledIntoIntegrationEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Catches up the consultations of a merged patient record, whatever merged
 * it. The identification at the desk already transfers them synchronously
 * (see ReconcileAdmissionPatientHandler), so this is then a no-op.
 */
#[AsMessageHandler(bus: 'messenger.bus.integration_event')]
final readonly class PatientReconciledIntoHandler
{
    public function __construct(
        private ConsultationRepositoryInterface $consultations,
    ) {
    }

    public function __invoke(PatientReconciledIntoIntegrationEvent $event): void
    {
        $this->consultations->transferToPatient(
            ClinicId::fromString($event->clinicId),
            PatientId::fromString($event->sourcePatientId),
            PatientId::fromString($event->targetPatientId),
        );
    }
}
