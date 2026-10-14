<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\GetCashDesk;

final readonly class CashClosingView
{
    public function __construct(
        public int $number,
        public string $businessDate,
        public \DateTimeImmutable $closedAt,
    ) {
    }
}
