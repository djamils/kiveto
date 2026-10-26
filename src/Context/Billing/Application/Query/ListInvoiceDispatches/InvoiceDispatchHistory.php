<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\ListInvoiceDispatches;

final readonly class InvoiceDispatchHistory
{
    /**
     * @param list<InvoiceDispatchView> $dispatches    oldest first
     * @param bool                      $remindedToday reminded already today (Europe/Paris): « Relancer » waits for tomorrow
     */
    public function __construct(
        public array $dispatches,
        public int $reminderCount,
        public bool $remindedToday,
    ) {
    }
}
