<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Notification;

/**
 * One bell entry as the dropdown and the history page show it.
 */
final readonly class NotificationItem
{
    /**
     * @param string      $kind   NEW_APPOINTMENT | CRITICAL_STOCK | EXPIRING_LOTS | SMS_QUOTA
     * @param string      $filter the bell filter it is listed under (« activite »)
     * @param string|null $url    the screen it opens (null when its route no longer exists)
     * @param string      $ago    « à l'instant », « 5 min », « 2 h », « 3 j »
     * @param string      $day    « Aujourd'hui », « Hier », « Lundi 19 octobre »
     * @param string      $time   « 9h30 »
     */
    public function __construct(
        public string $id,
        public string $kind,
        public string $filter,
        public string $title,
        public string $detail,
        public ?string $url,
        public string $ago,
        public string $day,
        public string $time,
        public bool $read,
    ) {
    }
}
