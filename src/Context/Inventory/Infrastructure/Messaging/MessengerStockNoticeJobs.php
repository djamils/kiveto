<?php

declare(strict_types=1);

namespace App\Context\Inventory\Infrastructure\Messaging;

use App\Context\Inventory\Application\Command\NotifyCriticalStock\NotifyCriticalStock;
use App\Context\Inventory\Application\Port\StockNoticeJobsInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * NotifyCriticalStock is routed to `async` (messenger.yaml) and dispatched
 * on the raw command bus — the application command bus expects a handled
 * result. The Doctrine transport writes it in the current transaction: a
 * stock change that is rolled back announces nothing.
 */
final readonly class MessengerStockNoticeJobs implements StockNoticeJobsInterface
{
    public function __construct(
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $commandBus,
    ) {
    }

    public function notifyCriticalStock(string $clinicId, string $alertId): void
    {
        $this->commandBus->dispatch(new NotifyCriticalStock($clinicId, $alertId));
    }
}
