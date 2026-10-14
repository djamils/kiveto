<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\OrderBook;

use App\Context\Procurement\Application\Port\OrderBookReadRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListSupplyOffersHandler
{
    public function __construct(private OrderBookReadRepositoryInterface $orderBook)
    {
    }

    /**
     * @return list<SupplyOffer>
     */
    public function __invoke(ListSupplyOffers $query): array
    {
        $gtins = array_values(array_unique(array_filter($query->gtins, static fn (string $gtin): bool => '' !== $gtin)));
        $codes = array_values(array_unique(array_filter($query->productCodes, static fn (string $code): bool => '' !== $code)));

        if ([] === $gtins && [] === $codes) {
            return [];
        }

        return $this->orderBook->supplyOffers($query->clinicId, $gtins, $codes);
    }
}
