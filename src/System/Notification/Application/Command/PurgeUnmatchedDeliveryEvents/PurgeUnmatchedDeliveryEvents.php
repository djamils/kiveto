<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\PurgeUnmatchedDeliveryEvents;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Daily: provider events kept for more than 24 h are matched one last
 * time, then dropped (each one logged).
 */
final readonly class PurgeUnmatchedDeliveryEvents implements CommandInterface
{
}
