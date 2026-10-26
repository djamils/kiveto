<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\RequestDueReminders;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The 15-minute scan of the `scheduling` schedule: one reminder request per
 * appointment whose reminder is due.
 */
final readonly class RequestDueReminders implements CommandInterface
{
}
