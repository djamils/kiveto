<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Delivery;

use App\System\Notification\Domain\Message\Channel;

/**
 * A provider event received for a message id not recorded yet (the webhook
 * was faster than the dispatch outcome): kept, then replayed as soon as a
 * message carries that id, and dropped after a day.
 */
final readonly class UnmatchedDeliveryEvent
{
    public function __construct(
        public string $id,
        public Channel $channel,
        public string $externalId,
        public DeliveryEvent $event,
        public \DateTimeImmutable $receivedAt,
    ) {
    }
}
