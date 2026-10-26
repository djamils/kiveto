<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\NotifyExpiringLots;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The daily « Péremption proche » scan (`inventory` schedule): the lots that
 * expire within 30 days and were never announced, one bell entry per clinic.
 */
final readonly class NotifyExpiringLots implements CommandInterface
{
}
