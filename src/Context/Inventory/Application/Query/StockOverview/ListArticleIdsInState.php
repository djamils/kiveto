<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\StockOverview;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Which articles of the clinic are in a given stock state. The stock page uses
 * it to narrow a catalogue listing without either context reading the other's
 * tables.
 */
final readonly class ListArticleIdsInState implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public StockStateFilter $state,
        /** How many days ahead counts as "expiring". */
        public int $expiringWithinDays = 30,
    ) {
    }
}
