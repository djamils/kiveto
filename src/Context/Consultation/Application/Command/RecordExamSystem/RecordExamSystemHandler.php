<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\RecordExamSystem;

use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\BodySystem;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\ExamStatus;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecordExamSystemHandler
{
    public function __construct(
        private ConsultationRepositoryInterface $consultations,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RecordExamSystem $command): void
    {
        $consultation = $this->consultations->findById(ConsultationId::fromString($command->consultationId));

        if (null === $consultation || $consultation->getClinicId()->toString() !== $command->clinicId) {
            throw new \DomainException('Consultation not found');
        }

        $system = BodySystem::tryFrom($command->system);

        if (null === $system) {
            throw new \InvalidArgumentException('Unknown body system');
        }

        $status = ExamStatus::tryFrom($command->status);

        if (null === $status) {
            throw new \InvalidArgumentException('Unknown exam status');
        }

        $consultation->recordExamSystem(
            $system,
            $status,
            $command->notes,
            $command->structuredData,
            UserId::fromString($command->recordedByUserId),
            $this->clock->now(),
        );

        $this->consultations->save($consultation);
    }
}
