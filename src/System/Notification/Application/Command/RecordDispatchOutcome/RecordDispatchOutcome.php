<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\RecordDispatchOutcome;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « The provider accepted the message under this id » (externalId) or « it
 * could not be handed over » (error): recorded in a short transaction, after
 * the network call.
 */
final readonly class RecordDispatchOutcome implements CommandInterface
{
    /**
     * @param string $occurredAt ATOM
     */
    public function __construct(
        public string $messageId,
        public ?string $externalId,
        public ?string $error,
        public string $occurredAt,
    ) {
    }
}
