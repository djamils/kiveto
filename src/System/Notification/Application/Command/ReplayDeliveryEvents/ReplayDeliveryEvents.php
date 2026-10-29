<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\ReplayDeliveryEvents;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Applies the provider events kept for one provider id, now recorded on a
 * message (`async`): each message in its own transaction, so that a
 * conflict on one never rolls back the re-matching of the others.
 */
final readonly class ReplayDeliveryEvents implements CommandInterface
{
    /**
     * @param string $channel EMAIL or SMS
     */
    public function __construct(
        public string $channel,
        public string $externalId,
    ) {
    }
}
