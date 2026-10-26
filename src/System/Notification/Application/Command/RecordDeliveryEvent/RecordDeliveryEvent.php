<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\RecordDeliveryEvent;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « The provider reports this about its message `externalId` »: a delivery
 * status (kind = a DeliveryStatus name: SENT, DEFERRED, DELIVERED, BOUNCED,
 * REJECTED, FAILED) or COMPLAINT (spam). Sent by the webhook consumers and
 * by the local delivery simulator.
 */
final readonly class RecordDeliveryEvent implements CommandInterface
{
    /**
     * @param string $channel    EMAIL | SMS
     * @param string $occurredAt ATOM, as the provider reports it
     */
    public function __construct(
        public string $channel,
        public string $externalId,
        public string $kind,
        public string $occurredAt,
        public ?string $reason = null,
    ) {
    }
}
