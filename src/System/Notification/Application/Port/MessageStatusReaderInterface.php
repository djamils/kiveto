<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Port;

use App\System\Notification\Application\Query\GetMessageStatuses\MessageStatusView;

/**
 * Reads the current status of many messages in one go.
 */
interface MessageStatusReaderInterface
{
    /**
     * @param list<string> $messageIds valid UUIDs
     *
     * @return array<string, MessageStatusView> by message id, unknown ids left out
     */
    public function statuses(array $messageIds): array;
}
