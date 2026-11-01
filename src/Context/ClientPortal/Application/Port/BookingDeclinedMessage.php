<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/**
 * « Your request was not confirmed », by SMS to the phone the request was
 * made with (the new client has no client file to be messaged through).
 */
final readonly class BookingDeclinedMessage
{
    /**
     * @param string $phone     E.164
     * @param string $slotLabel « jeudi 22/10 à 9h30 », in the clinic's time zone
     */
    public function __construct(
        public string $requestId,
        public string $phone,
        public string $recipientName,
        public string $clinicName,
        public string $slotLabel,
    ) {
    }
}
