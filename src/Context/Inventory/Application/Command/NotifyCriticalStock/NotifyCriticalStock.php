<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\NotifyCriticalStock;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Announces a new critical stock alert in the bell (« Stock critique »).
 * Queued on `async` by the live projection of the stock alerts.
 */
final readonly class NotifyCriticalStock implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $alertId,
    ) {
    }
}
