<?php

declare(strict_types=1);

namespace App\ClinicalCare\Application\Command\AddClinicalNote;

use App\ClinicalCare\Domain\Repository\ConsultationRepositoryInterface;
use App\ClinicalCare\Domain\ValueObject\ConsultationId;
use App\ClinicalCare\Domain\ValueObject\NoteType;
use App\ClinicalCare\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AddClinicalNoteHandler
{
    public function __construct(
        private ConsultationRepositoryInterface $consultations,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AddClinicalNote $command): void
    {
        $consultationId = ConsultationId::fromString($command->consultationId);
        $consultation   = $this->consultations->findById($consultationId);

        if (null === $consultation) {
            throw new \DomainException('Consultation not found');
        }

        $noteType        = NoteType::from($command->noteType);
        $createdByUserId = UserId::fromString($command->createdByUserId);
        $now             = $this->clock->now();

        $consultation->addClinicalNote(
            $noteType,
            $command->content,
            $createdByUserId,
            $now,
        );

        $this->consultations->save($consultation);
    }
}
