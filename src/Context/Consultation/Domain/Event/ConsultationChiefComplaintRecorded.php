<?php

declare(strict_types=1);

namespace App\Context\Consultation\Domain\Event;

use App\Context\Consultation\Domain\ValueObject\ConsultationId;
use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class ConsultationChiefComplaintRecorded extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'consultation';
    protected const int VERSION            = 1;

    public function __construct(
        public ConsultationId $consultationId,
        public string $chiefComplaint,
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
            'consultationId' => $this->consultationId->toString(),
            'chiefComplaint' => $this->chiefComplaint,
            'occurredOn'     => $this->occurredOn->format(\DateTimeInterface::ATOM),
        ];
    }
}
