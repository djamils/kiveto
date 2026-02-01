<?php

declare(strict_types=1);

namespace App\ClinicalCare\Domain\Event;

use App\ClinicalCare\Domain\ValueObject\ClinicalNoteRecord;
use App\ClinicalCare\Domain\ValueObject\ConsultationId;
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
            'noteType'        => $this->note->noteType->value,
            'content'         => $this->note->content,
            'createdAt'       => $this->note->createdAt->format(\DateTimeInterface::ATOM),
            'createdByUserId' => $this->note->createdByUserId->toString(),
            'occurredOn'      => $this->occurredOn->format(\DateTimeInterface::ATOM),
        ];
    }
}
