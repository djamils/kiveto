<?php

declare(strict_types=1);

namespace App\ClinicalCare\Domain\Event;

use App\ClinicalCare\Domain\ValueObject\ConsultationId;
use App\ClinicalCare\Domain\ValueObject\Vitals;
use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class ConsultationVitalsRecorded extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'clinical-care';
    protected const int VERSION            = 1;

    public function __construct(
        public ConsultationId $consultationId,
        public Vitals $vitals,
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
            'weightKg'       => $this->vitals->weightKg,
            'temperatureC'   => $this->vitals->temperatureC,
            'occurredOn'     => $this->occurredOn->format(\DateTimeInterface::ATOM),
        ];
    }
}
