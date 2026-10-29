<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Dispatch;

use App\Context\Billing\Application\Port\ClientContact;
use App\Context\Billing\Application\Port\OutstandingInvoiceRow;

/**
 * An invoice that may be reminded now, and the channels it would go on.
 */
final readonly class ReminderCandidate
{
    /**
     * @param list<string> $channels EMAIL and / or SMS
     */
    public function __construct(
        public OutstandingInvoiceRow $invoice,
        public ClientContact $contact,
        public array $channels,
    ) {
    }

    public function sendsOn(string $channel): bool
    {
        return \in_array($channel, $this->channels, true);
    }
}
