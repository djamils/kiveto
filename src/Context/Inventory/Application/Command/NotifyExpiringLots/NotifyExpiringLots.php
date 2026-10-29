<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\NotifyExpiringLots;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The « Péremption proche » scan of a clinic: its lots that expire within
 * 30 days and were never announced, one bell entry. Without a clinic id
 * (the daily `inventory` schedule), queues one such scan per clinic that
 * has candidate lots (asynchronous jobs: one clinic never blocks another).
 */
final readonly class NotifyExpiringLots implements CommandInterface
{
    public function __construct(public ?string $clinicId = null)
    {
    }
}
