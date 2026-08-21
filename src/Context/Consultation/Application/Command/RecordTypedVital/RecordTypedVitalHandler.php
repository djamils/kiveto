<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\RecordTypedVital;

use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Context\Consultation\Domain\ValueObject\VitalType;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecordTypedVitalHandler
{
    public function __construct(
        private ConsultationRepositoryInterface $consultations,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RecordTypedVital $command): void
    {
        $consultation = $this->consultations->findById(ConsultationId::fromString($command->consultationId));

        if (null === $consultation || $consultation->getClinicId()->toString() !== $command->clinicId) {
            throw new \DomainException('Consultation not found');
        }

        $type = VitalType::tryFrom($command->type);

        if (null === $type) {
            throw new \InvalidArgumentException('Unknown vital type');
        }

        $consultation->recordTypedVital(
            $type,
            $command->value,
            UserId::fromString($command->recordedByUserId),
            $this->clock->now(),
        );

        $this->consultations->save($consultation);
    }
}
