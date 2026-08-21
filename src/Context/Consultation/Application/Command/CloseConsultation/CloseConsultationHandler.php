<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\CloseConsultation;

use App\Context\Consultation\Application\Port\AdmissionServiceCoordinatorInterface;
use App\Context\Consultation\Application\Port\ConsultationReadRepositoryInterface;
use App\Context\Consultation\Application\Port\SchedulingServiceCoordinatorInterface;
use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CloseConsultationHandler
{
    public function __construct(
        private ConsultationRepositoryInterface $consultations,
        private ConsultationReadRepositoryInterface $consultationsRead,
        private SchedulingServiceCoordinatorInterface $schedulingCoordinator,
        private AdmissionServiceCoordinatorInterface $admissionCoordinator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CloseConsultation $command): void
    {
        $consultationId = ConsultationId::fromString($command->consultationId);
        $consultation   = $this->consultations->findById($consultationId);

        if (null === $consultation) {
            throw new \DomainException('Consultation not found');
        }

        $closedByUserId = UserId::fromString($command->closedByUserId);

        $consultation->close(
            $closedByUserId,
            $command->summary,
            $this->clock->now(),
        );

        $this->consultations->save($consultation);

        // If linked to appointment => complete appointment
        if ($appointmentId = $consultation->getAppointmentId()) {
            $this->schedulingCoordinator->completeAppointment($appointmentId, $closedByUserId);
        }

        // The patient goes back to the desk to be charged, unless another
        // consultation of the same visit is still running.
        $stillRunning = $this->consultationsRead->hasOpenConsultationForAdmission(
            $consultation->getAdmissionId(),
            $consultation->getClinicId(),
            $consultationId,
        );

        if (!$stillRunning) {
            $this->admissionCoordinator->sendToReception(
                $consultation->getAdmissionId()->toString(),
                $consultation->getClinicId()->toString(),
            );
        }
    }
}
