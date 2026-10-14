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

        // Both readings removed from the cockpit: the consultation goes back to
        // "not measured" rather than refusing the change.
        if (null === $command->weightKg && null === $command->temperatureC) {
            $consultation->clearVitals($this->clock->now());
        } else {
            $consultation->recordVitals(
                Vitals::create($command->weightKg, $command->temperatureC),
                $this->clock->now(),
            );
        }

        $this->consultations->save($consultation);
    }
}
