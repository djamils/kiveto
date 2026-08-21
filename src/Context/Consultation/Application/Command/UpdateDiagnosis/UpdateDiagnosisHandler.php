<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\UpdateDiagnosis;

use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\DiagnosisCertainty;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateDiagnosisHandler
{
    public function __construct(
        private ConsultationRepositoryInterface $consultations,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateDiagnosis $command): void
    {
        $consultation = $this->consultations->findById(ConsultationId::fromString($command->consultationId));

        if (null === $consultation || $consultation->getClinicId()->toString() !== $command->clinicId) {
            throw new \DomainException('Consultation not found');
        }

        $certainty = DiagnosisCertainty::tryFrom($command->certainty);

        if (null === $certainty) {
            throw new \InvalidArgumentException('Unknown diagnosis certainty');
        }

        $consultation->updateDiagnosis(
            $command->diagnosisId,
            $command->code,
            $command->label,
            $certainty,
            $command->note,
            $this->clock->now(),
        );

        $this->consultations->save($consultation);
    }
}
