<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class MicrochipRegistryLookupFound extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'regulatory';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $lookupId,
        public string $chipNumber,
        public string $registryAnimalData,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->lookupId;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'lookupId'           => $this->lookupId,
            'chipNumber'         => $this->chipNumber,
            'registryAnimalData' => $this->registryAnimalData,
        ];
    }
}
