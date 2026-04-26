<?php

declare(strict_types=1);

namespace App\Context\Admission\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class AdmissionTriageDeescalated extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'admission';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $admissionId,
        public string $oldLevel,
        public string $newLevel,
        public string $occurredAt,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->admissionId;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'admissionId' => $this->admissionId,
            'oldLevel'    => $this->oldLevel,
            'newLevel'    => $this->newLevel,
            'occurredAt'  => $this->occurredAt,
        ];
    }
}
