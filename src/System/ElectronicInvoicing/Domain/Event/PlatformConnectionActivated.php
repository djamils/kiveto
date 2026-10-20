<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class PlatformConnectionActivated extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'electronic_invoicing';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $connectionId,
        public string $organisationId,
        public string $activatedAt,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->connectionId;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return ['connectionId' => $this->connectionId, 'organisationId' => $this->organisationId, 'activatedAt' => $this->activatedAt];
    }
}
