<?php

declare(strict_types=1);

namespace App\Context\Animal\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class AnimalOwnersReplaced extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'animal';
    protected const int    VERSION         = 1;

    /**
     * @param list<string> $secondaryOwnerClientIds
     */
    public function __construct(
        public string $animalId,
        public string $clinicId,
        public string $primaryOwnerClientId,
        public array $secondaryOwnerClientIds,
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
            'animalId'                => $this->animalId,
            'clinicId'                => $this->clinicId,
            'primaryOwnerClientId'    => $this->primaryOwnerClientId,
            'secondaryOwnerClientIds' => $this->secondaryOwnerClientIds,
        ];
    }
}
