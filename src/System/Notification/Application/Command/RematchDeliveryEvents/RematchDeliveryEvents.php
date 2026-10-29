<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\RematchDeliveryEvents;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Every minute or so: kept provider events whose message id is now recorded
 * are applied — closes the race between a fast webhook and the dispatch
 * outcome committed just after it.
 */
final readonly class RematchDeliveryEvents implements CommandInterface
{
}
