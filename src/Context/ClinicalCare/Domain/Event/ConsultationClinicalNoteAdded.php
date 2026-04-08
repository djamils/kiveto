<?php

declare(strict_types=1);

namespace App\Context\ClinicalCare\Domain\Event;

use App\Context\ClinicalCare\Domain\ValueObject\ClinicalNoteRecord;
use App\Context\ClinicalCare\Domain\ValueObject\ConsultationId;
use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class ConsultationClinicalNoteAdded extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'clinical-care';
    protected const int VERSION            = 1;

    public function __construct(
        public ConsultationId $consultationId,
        public ClinicalNoteRecord $note,
        public \DateTimeImmutable $occurredOn,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->consultationId->toString();
    }

    public function payload(): array
    {
        return [
            'consultationId'  => $this->consultationId->toString(),
            'noteType'        => $this->note->getNoteType()->value,
            'content'         => $this->note->getContent(),
            'createdAt'       => $this->note->getCreatedAtUtc()->format(\DateTimeInterface::ATOM),
            'createdByUserId' => $this->note->getCreatedByUserId(),
            'occurredOn'      => $this->occurredOn->format(\DateTimeInterface::ATOM),
        ];
    }
}
