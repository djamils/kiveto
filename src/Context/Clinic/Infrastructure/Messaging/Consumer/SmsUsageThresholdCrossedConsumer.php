<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Messaging\Consumer;

use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Notification\Application\Command\PostInAppNotification\PostInAppNotification;
use App\System\Notification\Domain\Event\SmsUsageThresholdCrossedIntegrationEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The clinic's administrators hear in the bell when its SMS usage reaches
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
        private ClinicMembershipRepositoryInterface $memberships,
        private CommandBusInterface $commandBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SmsUsageThresholdCrossedIntegrationEvent $event): void
    {
        $administrators = $this->memberships->findEffectiveUserIds(
            ClinicId::fromString($event->clinicId),
            $this->clock->now(),
            administratorsOnly: true,
        );

        if ([] === $administrators) {
            return;
        }

        [$title, $detail] = self::wording($event);

        $this->commandBus->dispatch(new PostInAppNotification(
            clinicId: $event->clinicId,
            userIds: $administrators,
            kind: 'SMS_QUOTA',
            title: $title,
            detail: $detail,
            route: self::ROUTE,
            routeParams: [],
            idempotencyKey: \sprintf('sms-quota:%s:%s:%s', $event->clinicId, $event->month, $event->threshold),
        ));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function wording(SmsUsageThresholdCrossedIntegrationEvent $event): array
    {
        $usage = \sprintf('%s / %s segments utilisés ce mois-ci', self::number($event->used), self::number($event->quota));

        return match ($event->threshold) {
            '80'    => ['Quota SMS à 80 %', $usage],
            '100'   => ['Quota SMS atteint', $usage . ' · les SMS suivants sont facturés en dépassement'],
            default => ['Plafond de dépassement atteint — rappels basculés sur l’e-mail', \sprintf('Dépassement : %s segments (plafond %s)', self::number(max(0, $event->used - $event->quota)), self::number($event->cap))],
        };
    }

    private static function number(int $value): string
    {
        return number_format($value, 0, ',', "\u{202F}");
    }
}
