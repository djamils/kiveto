<?php

declare(strict_types=1);

namespace App\Clinic\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

readonly class ClinicCreated extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'clinic';
    protected const int    VERSION         = 1;

    public function __construct(
        private string $clinicId,
        private string $name,
        private string $slug,
        private string $timeZone,
        private string $locale,
        private ?string $clinicGroupId,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->clinicId;
    }

    public function payload(): array
    {
        return [
            'clinicId'      => $this->clinicId,
            'name'          => $this->name,
            'slug'          => $this->slug,
            'timeZone'      => $this->timeZone,
            'locale'        => $this->locale,
            'clinicGroupId' => $this->clinicGroupId,
        ];
    }
}
