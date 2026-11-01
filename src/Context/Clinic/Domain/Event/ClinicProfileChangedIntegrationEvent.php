<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Event;

use App\Shared\Domain\Event\AbstractIntegrationEvent;

/**
 * Something a public profile of the clinic shows may have changed: its name,
 * letterhead (address, phone), opening hours, time zone, or whether it (or its
 * group) is active. Thin by design: consumers re-read the clinic through
 * Clinic's queries (S7c).
 */
final readonly class ClinicProfileChangedIntegrationEvent extends AbstractIntegrationEvent
{
    protected const string BOUNDED_CONTEXT = 'clinic';
    protected const int    VERSION         = 1;

    public function __construct(public string $clinicId)
    {
    }

    public function aggregateId(): string
    {
        return $this->clinicId;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return ['clinicId' => $this->clinicId];
    }
}
