<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\StockOverview;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Everything the stock page starts from, for every article the clinic follows.
 */
final readonly class GetClinicStock implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public int $weeks = 12,
        public int $latestMovements = 5,
        public string $timeZone = 'Europe/Paris',
    ) {
    }
}
