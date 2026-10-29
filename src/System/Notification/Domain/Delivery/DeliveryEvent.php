<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Delivery;

use App\System\Notification\Domain\Message\DeliveryStatus;

/**
 * What a provider reported about a message it accepted (webhook, or the
 * local simulator faking one): a delivery status, or a complaint (the
 * recipient marked the message as spam — a flag, not a status).
 *
 * Its kind is the status name, or COMPLAINT; that is how it is stored while
 * the message it concerns is not known yet.
 */
final readonly class DeliveryEvent
{
    public const string COMPLAINT = 'COMPLAINT';

    private function __construct(
        public ?DeliveryStatus $status,
        public \DateTimeImmutable $occurredAt,
        public ?string $reason,
    ) {
    }

    public static function status(DeliveryStatus $status, \DateTimeImmutable $occurredAt, ?string $reason = null): self
    {
        if (\in_array($status, [DeliveryStatus::QUEUED, DeliveryStatus::BLOCKED_CAP], true)) {
            throw new \InvalidArgumentException(\sprintf('%s is not a status a provider reports.', $status->value));
        }

        return new self($status, $occurredAt, $reason);
    }

    public static function complaint(\DateTimeImmutable $occurredAt): self
    {
        return new self(null, $occurredAt, null);
    }

    /**
     * @throws \InvalidArgumentException when the kind is neither a provider status nor COMPLAINT
     */
    public static function ofKind(string $kind, \DateTimeImmutable $occurredAt, ?string $reason = null): self
    {
        if (self::COMPLAINT === $kind) {
            return self::complaint($occurredAt);
        }

        $status = DeliveryStatus::tryFrom($kind) ?? throw new \InvalidArgumentException(\sprintf('Unknown delivery event kind "%s".', $kind));

        return self::status($status, $occurredAt, $reason);
    }

    public function kind(): string
    {
        return $this->status->value ?? self::COMPLAINT;
    }
}
