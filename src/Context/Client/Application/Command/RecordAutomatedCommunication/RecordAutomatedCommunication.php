<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\RecordAutomatedCommunication;

use App\Shared\Application\Bus\CommandInterface;

/**
 * A status the Notification context reported for a message sent to a
 * client: journals the message on its first status, then follows it.
 * Reports may come twice or out of order; the sequence orders them.
 */
final readonly class RecordAutomatedCommunication implements CommandInterface
{
    /**
     * @param string $channel    Notification channel: EMAIL | SMS
     * @param string $status     Notification delivery status (QUEUED, SENT, DELIVERED…)
     * @param string $occurredAt ATOM date of the status
     */
    public function __construct(
        public string $notificationMessageId,
        public string $clinicId,
        public string $clientId,
        public string $channel,
        public string $subject,
        public string $recipientLabel,
        public string $status,
        public string $occurredAt,
        public int $sequence,
    ) {
    }
}
