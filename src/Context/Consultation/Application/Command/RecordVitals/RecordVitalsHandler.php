<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\RecordVitals;

use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\Vitals;
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
        $consultation   = $this->consultations->findById($consultationId);

        if (null === $consultation || $consultation->getClinicId()->toString() !== $command->clinicId) {
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
