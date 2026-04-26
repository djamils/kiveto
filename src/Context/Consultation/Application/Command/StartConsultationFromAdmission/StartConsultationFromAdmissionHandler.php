<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\StartConsultationFromAdmission;

use App\Context\Consultation\Application\Port\AdmissionContextProviderInterface;
use App\Context\Consultation\Application\Port\PractitionerEligibilityCheckerInterface;
use App\Context\Consultation\Domain\Consultation;
use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\AdmissionId;
use App\Context\Consultation\Domain\ValueObject\ClinicId;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\PatientId;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class StartConsultationFromAdmissionHandler
{
    public function __construct(
        private ConsultationRepositoryInterface $consultations,
        private PractitionerEligibilityCheckerInterface $eligibilityChecker,
        private AdmissionContextProviderInterface $admissionContextProvider,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(StartConsultationFromAdmission $command): string
    {
        $admissionId     = AdmissionId::fromString($command->admissionId);
        $startedByUserId = UserId::fromString($command->startedByUserId);
        $now             = $this->clock->now();

        // 1. Resolve patient and clinic from admission
        $admissionContext = $this->admissionContextProvider->getAdmissionContext($command->admissionId);
        $clinicId         = ClinicId::fromString($admissionContext->clinicId);
        $patientId        = PatientId::fromString($admissionContext->patientId);

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

        // 3. Create consultation
        $consultationId = ConsultationId::generate();

        $consultation = Consultation::startFromAdmission(
            $consultationId,
            $clinicId,
            $admissionId,
            $patientId,
            $startedByUserId,
            $now,
        );

        // 4. Persist
        $this->consultations->save($consultation);

        return $consultationId->toString();
    }
}
