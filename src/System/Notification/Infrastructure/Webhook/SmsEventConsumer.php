<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Webhook;

use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Notification\Application\Command\RecordDeliveryEvent\RecordDeliveryEvent;
use App\System\Notification\Domain\Message\Channel;
use App\System\Notification\Domain\Message\DeliveryStatus;
use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;

/**
 * Brevo SMS events, consumed by the worker, recorded as delivery events from
 * the raw (normalised) Brevo status: sent / accepted → SENT, delivered →
 * DELIVERED, soft bounce → DEFERRED (never final), hard bounce → BOUNCED,
 * rejected / blocked / skipped → REJECTED, error → FAILED. Replies and
 * (un)subscriptions are ignored.
 */
#[AsRemoteEventConsumer('brevo_sms')]
final readonly class SmsEventConsumer implements ConsumerInterface
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function consume(RemoteEvent $event): void
    {
        $payload = $event->getPayload();
        $status  = $payload['msg_status'] ?? null;
        $kind    = \is_string($status) ? self::kind(BrevoSmsPayloadConverter::normalisedStatus($status)) : null;
        if (null === $kind) {
            return;
        }

        $reason = $payload['description'] ?? null;

        $this->commandBus->dispatch(new RecordDeliveryEvent(
            channel: Channel::SMS->value,
            externalId: $event->getId(),
            kind: $kind->value,
            occurredAt: BrevoSmsPayloadConverter::occurredAt($payload)->format(\DateTimeInterface::ATOM),
            reason: DeliveryStatus::SENT === $kind || DeliveryStatus::DELIVERED === $kind || !\is_string($reason) || '' === $reason ? null : $reason,
        ));
    }

    private static function kind(string $status): ?DeliveryStatus
    {
        return match ($status) {
            'sent', 'accepted' => DeliveryStatus::SENT,
            'delivered'  => DeliveryStatus::DELIVERED,
            'softbounce' => DeliveryStatus::DEFERRED,
            'hardbounce' => DeliveryStatus::BOUNCED,
            'rejected', 'blocked', 'skip', 'skipped' => DeliveryStatus::REJECTED,
            'error' => DeliveryStatus::FAILED,
            default => null,
        };
    }
}
