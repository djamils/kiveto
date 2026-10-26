<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\NotifyCriticalStock;

use App\Context\Inventory\Application\Notification\StockNoticeRecipients;
use App\Context\Inventory\Application\Port\StaffBellInterface;
use App\Context\Inventory\Application\Port\StockAlertReadRepositoryInterface;
use App\Context\Inventory\Domain\StockAlert\ValueObject\StockAlertType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * « Stock critique · <article> » to the clinic's assistants (or its
 * administrators), once per alert row (idempotency key). An alert already
 * resolved when the worker runs (restocked meanwhile) is not announced.
 */
#[AsMessageHandler]
final readonly class NotifyCriticalStockHandler
{
    public function __construct(
        private StockAlertReadRepositoryInterface $alerts,
        private StockNoticeRecipients $recipients,
        private StaffBellInterface $bell,
    ) {
    }

    public function __invoke(NotifyCriticalStock $command): void
    {
        $alert = $this->alerts->findById($command->alertId);

        if (null === $alert || null !== $alert->resolvedAt || $alert->clinicId->toString() !== $command->clinicId) {
            return;
        }

        $userIds = $this->recipients->of($command->clinicId);

        if ([] === $userIds) {
            return;
        }

        $this->bell->announceCriticalStock(
            clinicId: $command->clinicId,
            userIds: $userIds,
            articleId: $alert->articleId->toString(),
            title: 'Stock critique · ' . ('' !== $alert->articleName ? $alert->articleName : 'article sans nom'),
            detail: StockAlertType::OUT_OF_STOCK === $alert->type ? 'Rupture de stock' : 'Sous le seuil d’alerte',
            idempotencyKey: 'stock-alert:' . $command->alertId,
        );
    }
}
