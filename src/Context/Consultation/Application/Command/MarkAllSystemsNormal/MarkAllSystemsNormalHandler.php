<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Command\MarkAllSystemsNormal;

use App\Context\Consultation\Domain\Repository\ConsultationRepositoryInterface;
use App\Context\Consultation\Domain\ValueObject\BodySystem;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class MarkAllSystemsNormalHandler
{
    public function __construct(
        private ConsultationRepositoryInterface $consultations,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(MarkAllSystemsNormal $command): void
    {
        $consultation = $this->consultations->findById(ConsultationId::fromString($command->consultationId));

        if (null === $consultation || $consultation->getClinicId()->toString() !== $command->clinicId) {
            throw new \DomainException('Consultation not found');
        }

        $systems = [];

        foreach ($command->systems as $rawSystem) {
            $system = BodySystem::tryFrom($rawSystem);

            if (null === $system) {
                throw new \InvalidArgumentException('Unknown body system');
            }

            $systems[] = $system;
        }

        $consultation->markAllSystemsNormal(
            $systems,
            UserId::fromString($command->recordedByUserId),
            $this->clock->now(),
        );

        $this->consultations->save($consultation);
    }
}
