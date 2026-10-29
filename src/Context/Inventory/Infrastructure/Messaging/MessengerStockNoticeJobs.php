<?php

declare(strict_types=1);

namespace App\Context\Inventory\Infrastructure\Messaging;

use App\Context\Inventory\Application\Command\NotifyCriticalStock\NotifyCriticalStock;
use App\Context\Inventory\Application\Command\NotifyExpiringLots\NotifyExpiringLots;
use App\Context\Inventory\Application\Port\StockNoticeJobsInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * NotifyCriticalStock is routed to `async` (messenger.yaml) and dispatched
 * on the raw command bus — the application command bus expects a handled
 * result. The Doctrine transport writes it in the current transaction: a
 * stock change that is rolled back announces nothing. NotifyExpiringLots has
 * no route (the daily run without clinic id is handled where it is
 * dispatched): the per-clinic scans are sent to `async` by stamp.
 */
final readonly class MessengerStockNoticeJobs implements StockNoticeJobsInterface
{
    public const string TRANSPORT = 'async';

    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
    ) {
    }

    public function notifyCriticalStock(string $clinicId, string $alertId): void
    {
        $this->commandBus->dispatch(new NotifyCriticalStock($clinicId, $alertId));
    }

    public function notifyExpiringLots(string $clinicId): void
    {
        $this->commandBus->dispatch(new NotifyExpiringLots($clinicId), [new TransportNamesStamp([self::TRANSPORT])]);
    }
}
