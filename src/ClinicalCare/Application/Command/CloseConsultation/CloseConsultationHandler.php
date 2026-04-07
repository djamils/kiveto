<?php

declare(strict_types=1);

namespace App\ClinicalCare\Application\Command\CloseConsultation;

use App\ClinicalCare\Application\Port\SchedulingServiceCoordinatorInterface;
use App\ClinicalCare\Domain\Repository\ConsultationRepositoryInterface;
use App\ClinicalCare\Domain\ValueObject\ConsultationId;
use App\ClinicalCare\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CloseConsultationHandler
{
    public function __construct(
        private ConsultationRepositoryInterface $consultations,
        private SchedulingServiceCoordinatorInterface $schedulingCoordinator,
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
    }
}
