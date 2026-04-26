<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\StartConsultationFromAppointment;

use App\Context\Consultation\Application\Port\AdmissionContextProviderInterface;
use App\Context\Consultation\Application\Port\PractitionerEligibilityCheckerInterface;
use App\Context\Consultation\Application\Port\SchedulingAppointmentContextProviderInterface;
use App\Context\Consultation\Application\Port\SchedulingServiceCoordinatorInterface;
use App\Context\Consultation\Domain\Consultation;
use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\AdmissionId;
use App\Context\Consultation\Domain\ValueObject\AppointmentId;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\PatientId;
use App\Context\Consultation\Domain\ValueObject\UserId;
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
        private AdmissionContextProviderInterface $admissionContextProvider,
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

        // 3. Admission required before starting consultation
        if (null === $appointmentContext->admissionId) {
            throw new \DomainException(
                'Appointment must be checked-in before starting consultation (admission required)',
            );
        }

        // 4. Resolve patient from admission
        $admissionContext = $this->admissionContextProvider->getAdmissionContext($appointmentContext->admissionId);
        $admissionId      = AdmissionId::fromString($appointmentContext->admissionId);
        $patientId        = PatientId::fromString($admissionContext->patientId);

        // 5. Ensure appointment is in service
        $this->schedulingCoordinator->ensureAppointmentInService($appointmentId, $startedByUserId);

        // 6. Create consultation
        $consultationId = ConsultationId::generate();

        $consultation = Consultation::startFromAppointment(
            $consultationId,
            $clinicId,
            $appointmentId,
            $admissionId,
            $patientId,
            $startedByUserId,
            $now,
        );

        // 7. Persist
        $this->consultations->save($consultation);

        return $consultationId->toString();
    }
}
