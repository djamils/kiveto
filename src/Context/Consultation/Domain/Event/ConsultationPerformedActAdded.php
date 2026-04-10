<?php

declare(strict_types=1);

namespace App\Context\Consultation\Domain\Event;

use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Context\Consultation\Domain\ValueObject\PerformedActRecord;
use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class ConsultationPerformedActAdded extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'consultation';
    protected const int VERSION            = 1;

    public function __construct(
        public ConsultationId $consultationId,
        public PerformedActRecord $act,
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
            'label'           => $this->act->getLabel(),
            'quantity'        => $this->act->getQuantity(),
            'performedAt'     => $this->act->getPerformedAtUtc()->format(\DateTimeInterface::ATOM),
            'createdAt'       => $this->act->getCreatedAtUtc()->format(\DateTimeInterface::ATOM),
            'createdByUserId' => $this->act->getCreatedByUserId(),
            'occurredOn'      => $this->occurredOn->format(\DateTimeInterface::ATOM),
        ];
    }
}
