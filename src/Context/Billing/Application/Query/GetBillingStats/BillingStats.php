<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\GetBillingStats;

final readonly class BillingStats
{
    public function __construct(
        public int $collectedMinorUnits,
        public int $outstandingMinorUnits,
        public int $issuedCount,
        public int $outstandingCount,
        public string $currency,
    ) {
    }
}
