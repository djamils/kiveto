<?php

declare(strict_types=1);

namespace App\ClinicalCare\Application\Command\AddPerformedAct;

use App\ClinicalCare\Domain\Repository\ConsultationRepositoryInterface;
use App\ClinicalCare\Domain\ValueObject\ConsultationId;
use App\ClinicalCare\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AddPerformedActHandler
{
    public function __construct(
        private ConsultationRepositoryInterface $consultations,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AddPerformedAct $command): void
    {
        $consultationId = ConsultationId::fromString($command->consultationId);
        $consultation = $this->consultations->findById($consultationId);

        if (null === $consultation) {
            throw new \DomainException('Consultation not found');
        }

        $performedAt = new \DateTimeImmutable($command->performedAt);
        $createdByUserId = UserId::fromString($command->createdByUserId);
        $now = $this->clock->now();

        $consultation->addPerformedAct(
            $command->label,
            $command->quantity,
            $performedAt,
            $createdByUserId,
            $now,
        );

        $this->consultations->save($consultation);
    }
}
