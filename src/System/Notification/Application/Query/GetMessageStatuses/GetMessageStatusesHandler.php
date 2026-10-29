<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Query\GetMessageStatuses;

use App\System\Notification\Application\Port\MessageStatusReaderInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class GetMessageStatusesHandler
{
    public function __construct(private MessageStatusReaderInterface $reader)
    {
    }

    /**
     * @return array<string, MessageStatusView>
     */
    public function __invoke(GetMessageStatuses $query): array
    {
        $ids = array_values(array_unique(array_filter($query->messageIds, Uuid::isValid(...))));

        return [] === $ids ? [] : $this->reader->statuses($ids);
    }
}
