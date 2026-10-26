<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Query\GetMessageStatuses;

final readonly class MessageStatusView
{
    /**
     * @param string $status          DeliveryStatus name
     * @param string $statusLabel     French label, readable without colour
     * @param string $statusChangedAt ATOM
     */
    public function __construct(
        public string $messageId,
        public string $channel,
        public string $status,
        public string $statusLabel,
        public string $statusChangedAt,
    ) {
    }
}
