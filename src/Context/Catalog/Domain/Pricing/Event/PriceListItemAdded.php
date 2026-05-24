<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Pricing\Event;

use App\Shared\Domain\Event\AbstractDomainEvent;

final readonly class PriceListItemAdded extends AbstractDomainEvent
{
    protected const string BOUNDED_CONTEXT = 'catalog';
    protected const int    VERSION         = 1;

    public function __construct(
        public string $priceListId,
        public string $priceListItemId,
        public string $itemType,
        public string $itemId,
        public int $netPriceMinorUnits,
        public string $netPriceCurrency,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->priceListId;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'priceListId'        => $this->priceListId,
            'priceListItemId'    => $this->priceListItemId,
            'itemType'           => $this->itemType,
            'itemId'             => $this->itemId,
            'netPriceMinorUnits' => $this->netPriceMinorUnits,
            'netPriceCurrency'   => $this->netPriceCurrency,
        ];
    }
}
