<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class LotDepleted extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'inventory';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $stockItemId,
        public string $lotId,
        public string $lotNumber,
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
            'stockItemId' => $this->stockItemId,
            'lotId'       => $this->lotId,
            'lotNumber'   => $this->lotNumber,
        ];
    }
}
