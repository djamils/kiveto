<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\MarkAllInAppNotificationsRead;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Tout marquer lu »: every entry of the user in the current clinic.
 */
final readonly class MarkAllInAppNotificationsRead implements CommandInterface
{
    public function __construct(
        public string $userId,
        public string $clinicId,
    ) {
    }
}
