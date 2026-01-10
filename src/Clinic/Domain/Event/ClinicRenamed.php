<?php

declare(strict_types=1);

namespace App\Clinic\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

readonly class ClinicRenamed extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'clinic';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $clinicId,
        private string $newName,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->clinicId;
    }

    public function payload(): array
    {
        return [
            'clinicId' => $this->clinicId,
            'newName'  => $this->newName,
        ];
    }
}
