<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Messaging\Consumer;

use App\Context\Clinic\Application\Port\BellNotifierInterface;
use App\Context\Clinic\Application\Query\Staff\ListClinicMembersForNotification\ListClinicMembersForNotification;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\Notification\Domain\Event\SmsUsageThresholdCrossedIntegrationEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The clinic's administrators (active accounts) hear in the bell when its SMS usage reaches
 * 80 % or 100 % of the month's quota, or the overage cap. Notification
 * announces each threshold once a month; the idempotency key makes a
 * replayed event harmless.
 */
#[AsMessageHandler(bus: 'messenger.bus.integration_event')]
final readonly class SmsUsageThresholdCrossedConsumer
{
    /** The settings section that shows the usage. */
    public const string ROUTE = 'clinic_settings_notifications';

    public function __construct(
        private QueryBusInterface $queryBus,
        private BellNotifierInterface $bell,
    ) {
    }

    public function __invoke(SmsUsageThresholdCrossedIntegrationEvent $event): void
    {
        $administrators = $this->queryBus->ask(new ListClinicMembersForNotification($event->clinicId, administratorsOnly: true));
        \assert(\is_array($administrators));

        if ([] === $administrators) {
            return;
        }

        [$title, $detail] = self::wording($event);

        $this->bell->post(
            $event->clinicId,
            array_values(array_filter($administrators, \is_string(...))),
            'SMS_QUOTA',
            $title,
            $detail,
            self::ROUTE,
            \sprintf('sms-quota:%s:%s:%s', $event->clinicId, $event->month, $event->threshold),
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function wording(SmsUsageThresholdCrossedIntegrationEvent $event): array
    {
        $usage = \sprintf('%s / %s segments utilisés ce mois-ci', self::number($event->used), self::number($event->quota));

        return match ($event->threshold) {
            '80'    => ['Quota SMS à 80 %', $usage],
            '100'   => ['Quota SMS atteint', $usage . ' · les SMS suivants sont comptés en dépassement'],
            default => ['Plafond de dépassement atteint — rappels SMS non critiques basculés sur l’e-mail ou bloqués', \sprintf('Dépassement : %s segments (plafond %s)', self::number(max(0, $event->used - $event->quota)), self::number($event->cap))],
        };
    }

    private static function number(int $value): string
    {
        return number_format($value, 0, ',', "\u{202F}");
    }
}
