<?php

declare(strict_types=1);

namespace App\Context\ClinicalCare\Application\Command\StartConsultationFromAppointment;

use App\Context\ClinicalCare\Application\Port\PractitionerEligibilityCheckerInterface;
use App\Context\ClinicalCare\Application\Port\SchedulingAppointmentContextProviderInterface;
use App\Context\ClinicalCare\Application\Port\SchedulingServiceCoordinatorInterface;
use App\Context\ClinicalCare\Domain\Consultation;
use App\Context\ClinicalCare\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\ClinicalCare\Domain\ValueObject\AnimalId;
use App\Context\ClinicalCare\Domain\ValueObject\AppointmentId;
use App\Context\ClinicalCare\Domain\ValueObject\ClinicId;
use App\Context\ClinicalCare\Domain\ValueObject\ConsultationId;
use App\Context\ClinicalCare\Domain\ValueObject\OwnerId;
use App\Context\ClinicalCare\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class StartConsultationFromAppointmentHandler
{
    public function __construct(
        private ConsultationRepositoryInterface $consultations,
        private PractitionerEligibilityCheckerInterface $eligibilityChecker,
        private SchedulingAppointmentContextProviderInterface $appointmentContextProvider,
        private SchedulingServiceCoordinatorInterface $schedulingCoordinator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(StartConsultationFromAppointment $command): string
    {
        $appointmentId   = AppointmentId::fromString($command->appointmentId);
        $startedByUserId = UserId::fromString($command->startedByUserId);
        $now             = $this->clock->now();

        // 1. Get appointment context
        $appointmentContext = $this->appointmentContextProvider->getAppointmentContext($appointmentId);
        $clinicId           = ClinicId::fromString($appointmentContext->clinicId);

        // 2. Check eligibility (VETERINARY role required)
        $isEligible = $this->eligibilityChecker->isEligibleForClinicAt(
            $startedByUserId,
            $clinicId,
            $now,
            ['VETERINARY'],
        );
        if (!$isEligible) {
            throw new \DomainException('User is not eligible as practitioner for this clinic');
        }

        // 3. Check intake requirement (unless EMERGENCY bypass)
        $isEmergency = 'EMERGENCY' === $appointmentContext->arrivalMode;
        if (!$isEmergency && null === $appointmentContext->linkedWaitingRoomEntryId) {
            throw new \DomainException(
                'Appointment must be checked-in before starting consultation (waiting room entry required)',
            );
        }

        // 4. Ensure appointment is in service
        $this->schedulingCoordinator->ensureAppointmentInService($appointmentId, $startedByUserId);

        // 5. Create consultation
        $consultationId = ConsultationId::generate();
        $ownerId        = $appointmentContext->ownerId ? OwnerId::fromString($appointmentContext->ownerId) : null;
        $animalId       = $appointmentContext->animalId ? AnimalId::fromString($appointmentContext->animalId) : null;

        $consultation = Consultation::startFromAppointment(
            $consultationId,
            $clinicId,
            $appointmentId,
            $startedByUserId,
            $ownerId,
            $animalId,
            $now,
        );

        // 6. Persist
        $this->consultations->save($consultation);

        return $consultationId->toString();
    }
}
