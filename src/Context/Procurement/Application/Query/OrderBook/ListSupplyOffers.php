<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\OrderBook;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The offers of the clinic's suppliers for articles known by their barcode
 * (GTIN) or their reference. Procurement does not know the clinic's articles:
 * the caller names them by what a supplier catalogue can be matched on.
 */
final readonly class ListSupplyOffers implements QueryInterface
{
    /**
     * @param list<string> $gtins
     * @param list<string> $productCodes
     */
    public function __construct(
        public string $clinicId,
        public array $gtins,
        public array $productCodes,
    ) {
    }
}
