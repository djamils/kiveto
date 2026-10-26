<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Query\ListInAppNotifications;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The user's bell entries in the current clinic over the last 30 days,
 * newest first; `limit` for the dropdown, null for the full history page.
 */
final readonly class ListInAppNotifications implements QueryInterface
{
    public function __construct(
        public string $userId,
        public string $clinicId,
        public ?int $limit = null,
    ) {
    }
}
