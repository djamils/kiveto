<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Infrastructure\Persistence\RowAccessor;
use App\System\Notification\Domain\Delivery\DeliveryEvent;
use App\System\Notification\Domain\Delivery\UnmatchedDeliveryEvent;
use App\System\Notification\Domain\Delivery\UnmatchedDeliveryEventRepositoryInterface;
use App\System\Notification\Domain\Message\Channel;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * The payload keeps the event as `{kind, occurredAt, reason}`; dates are
 * written in UTC.
 */
final readonly class DbalUnmatchedDeliveryEventRepository implements UnmatchedDeliveryEventRepositoryInterface
{
    private const string TABLE = 'notification__unmatched_delivery_events';

    public function __construct(private Connection $connection)
    {
    }

    public function keep(UnmatchedDeliveryEvent $event): void
    {
        $this->connection->insert(self::TABLE, [
            'id'          => Uuid::fromString($event->id)->toBinary(),
            'channel'     => $event->channel->value,
            'external_id' => $event->externalId,
            'payload'     => json_encode([
                'kind'       => $event->event->kind(),
                'occurredAt' => self::utc($event->event->occurredAt)->format(\DateTimeInterface::ATOM),
                'reason'     => $event->event->reason,
            ], \JSON_THROW_ON_ERROR),
            'received_at' => self::utc($event->receivedAt)->format('Y-m-d H:i:s'),
        ]);
    }

    public function findFor(Channel $channel, string $externalId): array
    {
        return $this->select('e.channel = :channel AND e.external_id = :external', ['channel' => $channel->value, 'external' => $externalId]);
    }

    public function findMatchable(int $limit): array
    {
        return $this->select(
            'EXISTS (SELECT 1 FROM notification__outbound_messages m WHERE m.channel = e.channel AND m.external_message_id = e.external_id)',
            [],
            $limit,
        );
    }

    public function findReceivedBefore(\DateTimeImmutable $before): array
    {
        return $this->select('e.received_at < :before', ['before' => self::utc($before)->format('Y-m-d H:i:s')]);
    }

    public function delete(string $id): void
    {
        $this->connection->delete(self::TABLE, ['id' => Uuid::fromString($id)->toBinary()]);
    }

    /**
     * @param array<string, string> $parameters
     *
     * @return list<UnmatchedDeliveryEvent>
     */
    private function select(string $condition, array $parameters, ?int $limit = null): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT e.* FROM ' . self::TABLE . ' e WHERE ' . $condition . ' ORDER BY e.received_at, e.id'
            . (null !== $limit ? ' LIMIT ' . max(1, $limit) : ''),
            $parameters,
        );

        return array_map(self::event(...), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function event(array $row): UnmatchedDeliveryEvent
    {
        $payload = json_decode(RowAccessor::string($row, 'payload'), true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($payload));
        $kind       = $payload['kind'] ?? null;
        $occurredAt = $payload['occurredAt'] ?? null;
        $reason     = $payload['reason'] ?? null;
        \assert(\is_string($kind) && \is_string($occurredAt) && (null === $reason || \is_string($reason)));

        return new UnmatchedDeliveryEvent(
            id: RowAccessor::uuid($row, 'id'),
            channel: Channel::from(RowAccessor::string($row, 'channel')),
            externalId: RowAccessor::string($row, 'external_id'),
            event: DeliveryEvent::ofKind($kind, new \DateTimeImmutable($occurredAt), $reason),
            receivedAt: new \DateTimeImmutable(RowAccessor::string($row, 'received_at'), new \DateTimeZone('UTC')),
        );
    }

    private static function utc(\DateTimeImmutable $at): \DateTimeImmutable
    {
        return $at->setTimezone(new \DateTimeZone('UTC'));
    }
}
