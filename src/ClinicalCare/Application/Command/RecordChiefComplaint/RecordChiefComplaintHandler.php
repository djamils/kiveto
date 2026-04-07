<?php

declare(strict_types=1);

namespace App\ClinicalCare\Application\Command\RecordChiefComplaint;

use App\ClinicalCare\Domain\Repository\ConsultationRepositoryInterface;
use App\ClinicalCare\Domain\ValueObject\ConsultationId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecordChiefComplaintHandler
{
    public function __construct(
        private ConsultationRepositoryInterface $consultations,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RecordChiefComplaint $command): void
    {
        $consultationId = ConsultationId::fromString($command->consultationId);
        $consultation   = $this->consultations->findById($consultationId);

        if (null === $consultation) {
            throw new \DomainException('Consultation not found');
        }

        $consultation->recordChiefComplaint(
            $command->chiefComplaint,
            $this->clock->now(),
        );

        $this->consultations->save($consultation);
    }
}
