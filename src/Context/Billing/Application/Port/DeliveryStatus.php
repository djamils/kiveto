<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

/**
 * Where one message of a dispatch stands, as Notification tracks it.
 */
final readonly class DeliveryStatus
{
    /**
     * @param string $channel EMAIL or SMS
     * @param string $status  Notification's status name (QUEUED, DELIVERED, BOUNCED…)
     * @param string $label   « En cours », « Délivré », « Non délivré »…
     */
    public function __construct(
        public string $channel,
        public string $status,
        public string $label,
    ) {
    }
}
