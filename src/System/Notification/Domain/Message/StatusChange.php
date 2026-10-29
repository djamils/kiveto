<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Message;

/**
 * One line of a message's status history: the status, when it happened (as
 * the source reports it), who reported it and why.
 */
final readonly class StatusChange
{
    public function __construct(
        public DeliveryStatus $status,
        public \DateTimeImmutable $occurredAt,
        public string $source,
        public ?string $reason = null,
    ) {
    }

    /** Same status at the same instant: a provider duplicate. */
    public function sameAs(DeliveryStatus $status, \DateTimeImmutable $occurredAt): bool
    {
        return $this->status === $status && $this->occurredAt->getTimestamp() === $occurredAt->getTimestamp();
    }
}
