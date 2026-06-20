<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\Supplier\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class SupplierRegistered extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'procurement';
    protected const int VERSION            = 1;

    public function __construct(
        private string $supplierId,
        private string $name,
        private string $code,
        private string $type,
        private string $countryCode,
        private string $integrationMode,
        private ?string $adapterIdentifier,
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
            'supplierId'        => $this->supplierId,
            'name'              => $this->name,
            'code'              => $this->code,
            'type'              => $this->type,
            'countryCode'       => $this->countryCode,
            'integrationMode'   => $this->integrationMode,
            'adapterIdentifier' => $this->adapterIdentifier,
        ];
    }
}
