<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Infrastructure\Persistence\RowAccessor;
use App\System\Notification\Application\Port\MessageStatusReaderInterface;
use App\System\Notification\Application\Query\GetMessageStatuses\MessageStatusView;
use App\System\Notification\Domain\Message\DeliveryStatus;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalMessageStatusReader implements MessageStatusReaderInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function statuses(array $messageIds): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, channel, status, status_changed_at FROM notification__outbound_messages WHERE id IN (:ids)',
            ['ids' => array_map(static fn (string $id): string => Uuid::fromString($id)->toBinary(), $messageIds)],
            ['ids' => ArrayParameterType::BINARY],
        );

        $views = [];
        foreach ($rows as $row) {
            $id     = RowAccessor::uuid($row, 'id');
            $status = DeliveryStatus::from(RowAccessor::string($row, 'status'));

            $views[$id] = new MessageStatusView(
                messageId: $id,
                channel: RowAccessor::string($row, 'channel'),
                status: $status->value,
                statusLabel: $status->label(),
                statusChangedAt: new \DateTimeImmutable(RowAccessor::string($row, 'status_changed_at'), new \DateTimeZone('UTC'))->format(\DateTimeInterface::ATOM),
            );
        }

        return $views;
    }
}
