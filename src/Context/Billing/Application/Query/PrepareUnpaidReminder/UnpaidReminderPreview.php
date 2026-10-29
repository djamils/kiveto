<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\PrepareUnpaidReminder;

final readonly class UnpaidReminderPreview
{
    /**
     * @param list<string> $invoiceIds     the invoices that would be reminded — what « Relancer » then posts
     * @param int          $smsSegments    the SMS segments they would consume (before any cap fallback)
     * @param int          $remainingCount eligible invoices beyond the batch limit, left for a next reminder
     */
    public function __construct(
        public array $invoiceIds,
        public int $clientCount,
        public int $smsCount,
        public int $smsSegments,
        public int $skippedCount,
        public int $remainingCount = 0,
    ) {
    }
}
