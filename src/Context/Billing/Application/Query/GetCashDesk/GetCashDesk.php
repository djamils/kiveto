<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\GetCashDesk;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetCashDesk implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $businessDate,
        public \DateTimeImmutable $dayStart,
        public \DateTimeImmutable $dayEnd,
    ) {
    }
}
