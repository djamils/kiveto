<?php

declare(strict_types=1);

namespace App\ClinicalCare\Domain\Event;

use App\ClinicalCare\Domain\ValueObject\ConsultationId;
use App\ClinicalCare\Domain\ValueObject\PerformedActRecord;
use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class ConsultationPerformedActAdded extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'clinical-care';
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
            'label'           => $this->act->label,
            'quantity'        => $this->act->quantity,
            'performedAt'     => $this->act->performedAt->format(\DateTimeInterface::ATOM),
            'createdAt'       => $this->act->createdAt->format(\DateTimeInterface::ATOM),
            'createdByUserId' => $this->act->createdByUserId->toString(),
            'occurredOn'      => $this->occurredOn->format(\DateTimeInterface::ATOM),
        ];
    }
}
