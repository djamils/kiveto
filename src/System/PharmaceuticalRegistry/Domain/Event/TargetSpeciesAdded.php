<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class TargetSpeciesAdded extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'pharmaceutical_registry';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $marketingAuthorizationId,
        public string $speciesCode,
        public string $route,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->marketingAuthorizationId;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'marketingAuthorizationId' => $this->marketingAuthorizationId,
            'speciesCode'              => $this->speciesCode,
            'route'                    => $this->route,
        ];
    }
}
