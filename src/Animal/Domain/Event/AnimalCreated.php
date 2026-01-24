<?php

declare(strict_types=1);

namespace App\Animal\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class AnimalCreated extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'animal';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $animalId,
        public string $clinicId,
        public string $name,
        public string $primaryOwnerClientId,
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
            'animalId'             => $this->animalId,
            'clinicId'             => $this->clinicId,
            'name'                 => $this->name,
            'primaryOwnerClientId' => $this->primaryOwnerClientId,
        ];
    }
}
