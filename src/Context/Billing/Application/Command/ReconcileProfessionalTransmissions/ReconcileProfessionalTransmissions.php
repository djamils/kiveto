<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\ReconcileProfessionalTransmissions;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The hourly safety net: no professional document may silently stay untransmitted.
 */
final readonly class ReconcileProfessionalTransmissions implements CommandInterface
{
}
