<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class StockAdjusted extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'inventory';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $stockItemId,
        public string $clinicId,
        public string $articleId,
        public string $lotId,
        public string $deltaAmount,
        public string $deltaUnit,
        public string $newLotQuantityAmount,
        public string $reason,
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
            'stockItemId'          => $this->stockItemId,
            'clinicId'             => $this->clinicId,
            'articleId'            => $this->articleId,
            'lotId'                => $this->lotId,
            'deltaAmount'          => $this->deltaAmount,
            'deltaUnit'            => $this->deltaUnit,
            'newLotQuantityAmount' => $this->newLotQuantityAmount,
            'reason'               => $this->reason,
            'occurredAt'           => $this->occurredAt,
        ];
    }
}
