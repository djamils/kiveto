<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Delivery\Simulator;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Fakes the provider's delivery webhook for a message the simulator handed
 * over. Sent to the `async` transport with a DelayStamp.
 */
final readonly class AdvanceSimulatedDelivery implements CommandInterface
{
    public function __construct(
        public string $channel,
        public string $externalId,
        public string $recipient,
    ) {
    }
}
