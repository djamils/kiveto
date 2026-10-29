<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\PostInAppNotification;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Tell these users of this clinic, in the bell »: one entry per user,
 * at most once per user under the idempotency key (built by the poster,
 * e.g. `new-appointment:<appointmentId>`).
 */
final readonly class PostInAppNotification implements CommandInterface
{
    /**
     * @param list<string>          $userIds
     * @param string                $kind        NEW_APPOINTMENT | CRITICAL_STOCK | EXPIRING_LOTS | SMS_QUOTA
     * @param string                $route       Symfony route name of the screen the entry opens
     * @param array<string, string> $routeParams
     */
    public function __construct(
        public string $clinicId,
        public array $userIds,
        public string $kind,
        public string $title,
        public string $detail,
        public string $route,
        public array $routeParams,
        public string $idempotencyKey,
        public ?string $actorInitials = null,
    ) {
    }
}
