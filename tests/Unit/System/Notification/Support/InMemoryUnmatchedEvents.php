<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Support;

use App\System\Notification\Domain\Delivery\UnmatchedDeliveryEvent;
use App\System\Notification\Domain\Delivery\UnmatchedDeliveryEventRepositoryInterface;
use App\System\Notification\Domain\Message\Channel;
use App\System\Notification\Domain\Message\OutboundMessageRepositoryInterface;

final class InMemoryUnmatchedEvents implements UnmatchedDeliveryEventRepositoryInterface
{
    /** @var array<string, UnmatchedDeliveryEvent> */
    public array $events = [];

    public function __construct(private readonly ?OutboundMessageRepositoryInterface $messages = null)
    {
    }

    public function keep(UnmatchedDeliveryEvent $event): void
    {
        $this->events[$event->id] = $event;
    }

    public function findFor(Channel $channel, string $externalId): array
    {
        return array_values(array_filter($this->events, static fn (UnmatchedDeliveryEvent $e): bool => $e->channel === $channel && $e->externalId === $externalId));
    }

    public function findMatchable(int $limit): array
    {
        return \array_slice(array_values(array_filter(
            $this->events,
            fn (UnmatchedDeliveryEvent $e): bool => null !== $this->messages?->findByExternalId($e->channel, $e->externalId),
        )), 0, $limit);
    }

    public function findReceivedBefore(\DateTimeImmutable $before): array
    {
        return array_values(array_filter($this->events, static fn (UnmatchedDeliveryEvent $e): bool => $e->receivedAt < $before));
    }

    public function delete(string $id): void
    {
        unset($this->events[$id]);
    }
}
