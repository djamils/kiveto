<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\PurgePractitionerDayLocks;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The monthly run of the `scheduling` schedule: drops the practitioner-day
 * lock rows of days more than 30 days past.
 */
final readonly class PurgePractitionerDayLocks implements CommandInterface
{
}
