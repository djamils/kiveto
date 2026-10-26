<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\MarkInAppNotificationRead;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The user clicked one of their bell entries; someone else's entry is left as it is.
 */
final readonly class MarkInAppNotificationRead implements CommandInterface
{
    public function __construct(
        public string $notificationId,
        public string $userId,
    ) {
    }
}
