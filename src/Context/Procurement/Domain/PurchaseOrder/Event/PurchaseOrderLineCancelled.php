<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\PurchaseOrder\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class PurchaseOrderLineCancelled extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'procurement';
    protected const int VERSION            = 1;

    public function __construct(
        private string $purchaseOrderId,
        private string $clinicId,
        private string $lineId,
        private string $reason,
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
            'lineId'          => $this->lineId,
            'reason'          => $this->reason,
        ];
    }
}
