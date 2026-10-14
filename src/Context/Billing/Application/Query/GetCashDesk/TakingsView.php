<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\GetCashDesk;

final readonly class TakingsView
{
    /**
     * @param array<string, int> $collectedByMethod payment method => amount received
     * @param array<string, int> $refundedByMethod  payment method => amount handed back by credit notes
     */
    public function __construct(
        public array $collectedByMethod,
        public array $refundedByMethod,
    ) {
    }

    public function totalCollected(): int
    {
        return array_sum($this->collectedByMethod);
    }
}
