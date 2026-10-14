<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\GetBillingStats;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetBillingStats implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $to,
        /** An outstanding invoice issued before this is overdue; null counts none. */
        public ?\DateTimeImmutable $overdueIssuedBefore = null,
    ) {
    }
}
