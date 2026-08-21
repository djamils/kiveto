<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\UpdatePlanAction;

use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdatePlanActionHandler
{
    public function __construct(
        private ConsultationRepositoryInterface $consultations,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdatePlanAction $command): void
    {
        $consultation = $this->consultations->findById(ConsultationId::fromString($command->consultationId));

        if (null === $consultation || $consultation->getClinicId()->toString() !== $command->clinicId) {
            throw new \DomainException('Consultation not found');
        }

        $consultation->updatePlanAction(
            $command->planActionId,
            $command->description,
            $command->posology,
            $command->durationDays,
            $command->followUpDays,
            $command->quantity,
            $this->clock->now(),
        );

        $this->consultations->save($consultation);
    }
}
