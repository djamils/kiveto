<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\PurgeInAppNotifications;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Daily: bell entries older than 30 days are deleted.
 */
final readonly class PurgeInAppNotifications implements CommandInterface
{
}
