<?php

declare(strict_types=1);

namespace App\ClinicalCare\Application\Command\RecordVitals;

use App\ClinicalCare\Domain\Repository\ConsultationRepositoryInterface;
use App\ClinicalCare\Domain\ValueObject\ConsultationId;
use App\ClinicalCare\Domain\ValueObject\Vitals;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecordVitalsHandler
{
    public function __construct(
        private ConsultationRepositoryInterface $consultations,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RecordVitals $command): void
    {
        $consultationId = ConsultationId::fromString($command->consultationId);
        $consultation = $this->consultations->findById($consultationId);

        if (null === $consultation) {
            throw new \DomainException('Consultation not found');
        }

        $vitals = Vitals::create($command->weightKg, $command->temperatureC);

        $consultation->recordVitals(
            $vitals,
            $this->clock->now(),
        );

        $this->consultations->save($consultation);
    }
}
