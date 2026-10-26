<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\PrepareUnpaidReminder;

use App\Shared\Application\Bus\QueryInterface;

/**
 * What « Relancer » would do, for the confirmation shown before it
 * (« N clients · environ S SMS »). Returns an UnpaidReminderPreview.
 */
final readonly class PrepareUnpaidReminder implements QueryInterface
{
    /**
     * @param list<string>|null $invoiceIds null: every unpaid invoice of the clinic
     */
    public function __construct(
        public string $clinicId,
        public ?array $invoiceIds = null,
    ) {
    }
}
