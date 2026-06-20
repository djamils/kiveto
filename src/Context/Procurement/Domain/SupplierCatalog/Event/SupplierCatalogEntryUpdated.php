<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierCatalog\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class SupplierCatalogEntryUpdated extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'procurement';
    protected const int VERSION            = 1;

    public function __construct(
        private string $entryId,
        private string $supplierId,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->entryId;
    }

    public function payload(): array
    {
        return [
            'entryId'    => $this->entryId,
            'supplierId' => $this->supplierId,
        ];
    }
}
