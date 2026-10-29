<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Adapter\Notification;

use App\Context\Billing\Application\Port\DeliveryStatus;
use App\Context\Billing\Application\Port\DeliveryStatusReaderInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\Notification\Application\Query\GetMessageStatuses\GetMessageStatuses;
use App\System\Notification\Application\Query\GetMessageStatuses\MessageStatusView;

/**
 * Notification's GetMessageStatuses: one query for every message of an
 * invoice's history.
 */
final readonly class QueryBusDeliveryStatusReader implements DeliveryStatusReaderInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function statuses(array $messageIds): array
    {
        $views    = $this->queryBus->ask(new GetMessageStatuses($messageIds));
        $statuses = [];

        foreach (\is_array($views) ? $views : [] as $view) {
            if ($view instanceof MessageStatusView) {
                $statuses[$view->messageId] = new DeliveryStatus($view->channel, $view->status, $view->statusLabel);
            }
        }

        return $statuses;
    }
}
