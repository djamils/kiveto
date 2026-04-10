<?php

declare(strict_types=1);

namespace App\Context\Consultation\Domain\Event;

use App\Context\Consultation\Domain\ValueObject\ClinicalNoteRecord;
use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class ConsultationClinicalNoteAdded extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'consultation';
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
