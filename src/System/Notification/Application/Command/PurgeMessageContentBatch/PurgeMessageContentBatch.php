<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\PurgeMessageContentBatch;

use App\Shared\Application\Bus\CommandInterface;

/**
 * One batch of the nightly content purge (`async`): its own transaction and
 * retries, so that a conflict on one message never rolls back the others.
 */
final readonly class PurgeMessageContentBatch implements CommandInterface
{
    /**
     * @param list<string> $messageIds
     */
    public function __construct(public array $messageIds)
    {
    }
}
