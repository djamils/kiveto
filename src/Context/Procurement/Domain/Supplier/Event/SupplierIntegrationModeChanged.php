<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\Supplier\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class SupplierIntegrationModeChanged extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'procurement';
    protected const int VERSION            = 1;

    public function __construct(
        private string $supplierId,
        private string $newMode,
        private ?string $newAdapterIdentifier,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->supplierId;
    }

    /** @return array<string, string|null> */
    public function payload(): array
    {
        return [
            'supplierId'           => $this->supplierId,
            'newMode'              => $this->newMode,
            'newAdapterIdentifier' => $this->newAdapterIdentifier,
        ];
    }
}
