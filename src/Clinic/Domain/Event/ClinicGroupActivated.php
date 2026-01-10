<?php

declare(strict_types=1);

namespace App\Clinic\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

readonly class ClinicGroupActivated extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'clinic';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $clinicGroupId,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->clinicGroupId;
    }

    public function payload(): array
    {
        return [
            'clinicGroupId' => $this->clinicGroupId,
        ];
    }
}
