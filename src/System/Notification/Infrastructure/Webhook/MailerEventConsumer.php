<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Webhook;

use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Notification\Application\Command\RecordDeliveryEvent\RecordDeliveryEvent;
use App\System\Notification\Domain\Delivery\DeliveryEvent;
use App\System\Notification\Domain\Message\Channel;
use App\System\Notification\Domain\Message\DeliveryStatus;
use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\RemoteEvent;

/**
 * Brevo e-mail events, consumed by the worker (`ConsumeRemoteEventMessage`
 * on `async`), recorded as delivery events. The mapping reads the raw Brevo
 * event name: the bridge converter names both bounces BOUNCE, while a soft
 * bounce is only DEFERRED. Opens, clicks and unsubscriptions are ignored.
 */
#[AsRemoteEventConsumer('brevo_mailer')]
final readonly class MailerEventConsumer implements ConsumerInterface
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function consume(RemoteEvent $event): void
    {
        if (!$event instanceof AbstractMailerEvent) {
            throw new \InvalidArgumentException(\sprintf('A Brevo e-mail event was expected, got "%s".', get_debug_type($event)));
        }

        $kind = self::kind($event->getPayload()['event'] ?? null);
        if (null === $kind) {
            return;
        }

        $this->commandBus->dispatch(new RecordDeliveryEvent(
            channel: Channel::EMAIL->value,
            externalId: $event->getId(),
            kind: $kind,
            occurredAt: $event->getDate()->format(\DateTimeInterface::ATOM),
            reason: $event instanceof MailerDeliveryEvent && '' !== $event->getReason() ? $event->getReason() : null,
        ));
    }

    private static function kind(mixed $brevoEvent): ?string
    {
        return match ($brevoEvent) {
            'request'   => DeliveryStatus::SENT->value,
            'delivered' => DeliveryStatus::DELIVERED->value,
            'deferred', 'soft_bounce' => DeliveryStatus::DEFERRED->value,
            'hard_bounce' => DeliveryStatus::BOUNCED->value,
            'invalid_email', 'blocked', 'error' => DeliveryStatus::REJECTED->value,
            'spam', 'complaint' => DeliveryEvent::COMPLAINT,
            default => null,
        };
    }
}
