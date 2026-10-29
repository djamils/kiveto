<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Notification;

use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\ClinicSettingsView;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\GetClinicSettings;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Notification\Application\Query\CountUnreadInAppNotifications\CountUnreadInAppNotifications;
use App\System\Notification\Application\Query\ListInAppNotifications\InAppNotificationView;
use App\System\Notification\Application\Query\ListInAppNotifications\ListInAppNotifications;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The member's bell in the current clinic (D13): the latest entries of the
 * dropdown, the 30-day history and the unread count. Times are shown in the
 * clinic's time zone.
 */
final readonly class NotificationFeedBuilder
{
    /** Entries in the dropdown. */
    public const int DROPDOWN_SIZE = 20;

    private const array DAYS   = [1 => 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
    private const array MONTHS = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    public function __construct(
        private QueryBusInterface $queryBus,
        private UrlGeneratorInterface $urls,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<NotificationItem> newest first; every entry of the last 30 days when $limit is null
     */
    public function items(string $userId, string $clinicId, ?int $limit = self::DROPDOWN_SIZE): array
    {
        $entries  = $this->queryBus->ask(new ListInAppNotifications($userId, $clinicId, $limit));
        $settings = $this->queryBus->ask(new GetClinicSettings($clinicId));
        $now      = $this->clock->now()->setTimezone(new \DateTimeZone($settings instanceof ClinicSettingsView ? $settings->timeZone : 'Europe/Paris'));

        return array_values(array_map(
            fn (InAppNotificationView $entry): NotificationItem => $this->item($entry, $now),
            array_filter(\is_array($entries) ? $entries : [], static fn (mixed $e): bool => $e instanceof InAppNotificationView),
        ));
    }

    public function unread(string $userId, string $clinicId): int
    {
        $count = $this->queryBus->ask(new CountUnreadInAppNotifications($userId, $clinicId));

        return \is_int($count) ? $count : 0;
    }

    private function item(InAppNotificationView $entry, \DateTimeImmutable $now): NotificationItem
    {
        $at = new \DateTimeImmutable($entry->createdAt)->setTimezone($now->getTimezone());

        return new NotificationItem(
            id: $entry->id,
            kind: $entry->kind,
            filter: $entry->filter,
            title: $entry->title,
            detail: $entry->detail,
            url: $this->url($entry),
            ago: self::ago($now->getTimestamp() - $at->getTimestamp()),
            day: self::day($at, $now),
            time: $at->format('G') . 'h' . $at->format('i'),
            read: $entry->read,
        );
    }

    private function url(InAppNotificationView $entry): ?string
    {
        try {
            return $this->urls->generate($entry->route, $entry->routeParams);
        } catch (RoutingException) {
            return null;
        }
    }

    private static function ago(int $seconds): string
    {
        return match (true) {
            $seconds < 60    => 'à l’instant',
            $seconds < 3600  => intdiv($seconds, 60) . ' min',
            $seconds < 86400 => intdiv($seconds, 3600) . ' h',
            default          => intdiv($seconds, 86400) . ' j',
        };
    }

    private static function day(\DateTimeImmutable $at, \DateTimeImmutable $now): string
    {
        return match ($at->format('Y-m-d')) {
            $now->format('Y-m-d')                   => 'Aujourd’hui',
            $now->modify('-1 day')->format('Y-m-d') => 'Hier',
            default                                 => self::DAYS[(int) $at->format('N')] . ' ' . $at->format('j') . ' ' . self::MONTHS[(int) $at->format('n')],
        };
    }
}
