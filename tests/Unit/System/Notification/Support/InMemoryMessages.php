<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Support;

use App\System\Notification\Domain\Exception\OutboundMessageNotFoundException;
use App\System\Notification\Domain\Message\Channel;
use App\System\Notification\Domain\Message\DeliveryStatus;
use App\System\Notification\Domain\Message\IdempotencyKey;
use App\System\Notification\Domain\Message\OutboundMessage;
use App\System\Notification\Domain\Message\OutboundMessageId;
use App\System\Notification\Domain\Message\OutboundMessageRepositoryInterface;

final class InMemoryMessages implements OutboundMessageRepositoryInterface
{
    /** @var array<string, OutboundMessage> */
    public array $messages = [];

    public function save(OutboundMessage $message): void
    {
        $this->messages[$message->id()->toString()] = $message;
    }

    public function get(OutboundMessageId $id): OutboundMessage
    {
        return $this->messages[$id->toString()] ?? throw new OutboundMessageNotFoundException($id->toString());
    }

    public function findByIdempotencyKey(IdempotencyKey $key): ?OutboundMessage
    {
        return array_find($this->messages, static fn (OutboundMessage $m): bool => $m->idempotencyKey()->toString() === $key->toString());
    }

    public function findByExternalId(Channel $channel, string $externalMessageId): ?OutboundMessage
    {
        return array_find($this->messages, static fn (OutboundMessage $m): bool => $m->channel() === $channel && $m->externalMessageId() === $externalMessageId);
    }

    public function sumBilledSegments(string $clinicId, \DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return array_sum(array_map(
            static fn (OutboundMessage $m): int => Channel::SMS === $m->channel() && $m->clinicId() === $clinicId && null !== $m->sentAt() && $m->sentAt() >= $from && $m->sentAt() < $to ? $m->segments() : 0,
            $this->messages,
        ));
    }

    public function sumQueuedSegments(string $clinicId): int
    {
        return array_sum(array_map(
            static fn (OutboundMessage $m): int => Channel::SMS === $m->channel() && $m->clinicId() === $clinicId && DeliveryStatus::QUEUED === $m->status() ? $m->segments() : 0,
            $this->messages,
        ));
    }

    /**
     * @return list<OutboundMessage>
     */
    public function all(): array
    {
        return array_values($this->messages);
    }
}
