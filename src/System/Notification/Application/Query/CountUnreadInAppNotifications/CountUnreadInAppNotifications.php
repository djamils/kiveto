<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Query\CountUnreadInAppNotifications;

use App\Shared\Application\Bus\QueryInterface;

/**
 * How many unread bell entries the user has in the current clinic (the dot).
 */
final readonly class CountUnreadInAppNotifications implements QueryInterface
{
    public function __construct(
        public string $userId,
        public string $clinicId,
    ) {
    }
}
