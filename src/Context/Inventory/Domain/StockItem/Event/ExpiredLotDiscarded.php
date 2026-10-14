<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

/**
 * What remained of an expired lot left the shelf. The quantity had already
 * left the usable stock when the lot expired; this is the boxes going in the
 * bin.
 */
final readonly class ExpiredLotDiscarded extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'inventory';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $stockItemId,
        public string $clinicId,
        public string $lotId,
        public string $lotNumber,
        public string $discardedQuantityAmount,
        public string $discardedQuantityUnit,
        public string $occurredAt,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->stockItemId;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'stockItemId'             => $this->stockItemId,
            'clinicId'                => $this->clinicId,
            'lotId'                   => $this->lotId,
            'lotNumber'               => $this->lotNumber,
            'discardedQuantityAmount' => $this->discardedQuantityAmount,
            'discardedQuantityUnit'   => $this->discardedQuantityUnit,
            'occurredAt'              => $this->occurredAt,
        ];
    }
}
