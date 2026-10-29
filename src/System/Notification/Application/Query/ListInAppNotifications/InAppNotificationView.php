<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Query\ListInAppNotifications;

final readonly class InAppNotificationView
{
    /**
     * @param string                $filter      bell filter the entry is listed under (« activite »)
     * @param array<string, string> $routeParams
     * @param string                $createdAt   ATOM
     */
    public function __construct(
        public string $id,
        public string $kind,
        public string $filter,
        public string $title,
        public string $detail,
        public string $route,
        public array $routeParams,
        public ?string $actorInitials,
        public string $createdAt,
        public bool $read,
    ) {
    }
}
