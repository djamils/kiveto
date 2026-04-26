<?php

declare(strict_types=1);

namespace App\Context\Animal\Domain\Event;

use App\Shared\Domain\Event\AbstractIntegrationEvent;

/**
 * Placeholder — this event will be fully implemented in Phase 2
 * when the Animal BC name-change flow is built out.
 *
 * Published by the Animal BC whenever an animal's name is changed.
 * Consumed by the Patient BC to update display labels of linked patients.
 *
 * Expected payload keys:
 *   - animalId (string)
 *   - clinicId (string)
 *   - newName  (string)
 *   - patientIds (list<string>) — IDs of patients linked to this animal
 */
final readonly class AnimalNameChangedIntegrationEvent extends AbstractIntegrationEvent
{
    protected const string BOUNDED_CONTEXT = 'animal';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $animalId,
        public string $clinicId,
        public string $newName,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->animalId;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'animalId' => $this->animalId,
            'clinicId' => $this->clinicId,
            'newName'  => $this->newName,
        ];
    }
}
