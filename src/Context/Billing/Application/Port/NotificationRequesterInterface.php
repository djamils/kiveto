<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

/**
 * Hands an invoice message to Notification, which renders, journals and
 * sends it; a replayed request (same idempotency key) never sends twice.
 */
interface NotificationRequesterInterface
{
    /** @return string the Notification message id */
    public function request(InvoiceMessage $message): string;
}
