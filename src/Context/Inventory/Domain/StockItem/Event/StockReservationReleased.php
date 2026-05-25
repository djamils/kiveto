<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class StockReservationReleased extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'inventory';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $stockItemId,
        public string $clinicId,
        public string $articleId,
        public string $quantityAmount,
        public string $quantityUnit,
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
            'stockItemId'    => $this->stockItemId,
            'clinicId'       => $this->clinicId,
            'articleId'      => $this->articleId,
            'quantityAmount' => $this->quantityAmount,
            'quantityUnit'   => $this->quantityUnit,
        ];
    }
}
