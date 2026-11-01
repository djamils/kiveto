<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\ScanExpiredPendingAppointments;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The 15-minute scan of the `scheduling` schedule: one decline per pending
 * request whose expiry has passed (P2).
 */
final readonly class ScanExpiredPendingAppointments implements CommandInterface
{
}
