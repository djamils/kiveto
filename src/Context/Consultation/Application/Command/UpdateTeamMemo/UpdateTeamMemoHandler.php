<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\UpdateTeamMemo;

use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateTeamMemoHandler
{
    public function __construct(
        private ConsultationRepositoryInterface $consultations,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateTeamMemo $command): void
    {
        $consultation = $this->consultations->findById(ConsultationId::fromString($command->consultationId));

        if (null === $consultation || $consultation->getClinicId()->toString() !== $command->clinicId) {
            throw new \DomainException('Consultation not found');
        }

        $consultation->updateTeamMemo($command->memo, $this->clock->now());

        $this->consultations->save($consultation);
    }
}
