<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\PurchaseOrder\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class PurchaseOrderConfirmed extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'procurement';
    protected const int VERSION            = 1;

    public function __construct(
        private string $purchaseOrderId,
        private string $clinicId,
        private string $confirmedAt,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->purchaseOrderId;
    }

    /** @return array<string, string> */
    public function payload(): array
    {
        return [
            'purchaseOrderId' => $this->purchaseOrderId,
            'clinicId'        => $this->clinicId,
            'confirmedAt'     => $this->confirmedAt,
        ];
    }
}
