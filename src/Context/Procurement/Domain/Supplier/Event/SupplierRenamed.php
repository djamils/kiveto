<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\Supplier\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class SupplierRenamed extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'procurement';
    protected const int VERSION            = 1;

    public function __construct(
        private string $supplierId,
        private string $oldName,
        private string $newName,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->supplierId;
    }

    /** @return array<string, string> */
    public function payload(): array
    {
        return [
            'supplierId' => $this->supplierId,
            'oldName'    => $this->oldName,
            'newName'    => $this->newName,
        ];
    }
}
