<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\ConfirmPurchaseOrder;

use App\Context\Procurement\Domain\PurchaseOrder\Exception\PurchaseOrderNotFoundException;
use App\Context\Procurement\Domain\PurchaseOrder\Repository\PurchaseOrderRepositoryInterface;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ConfirmPurchaseOrderHandler
{
    public function __construct(
        private PurchaseOrderRepositoryInterface $purchaseOrderRepository,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(ConfirmPurchaseOrder $command): void
    {
        $po = $this->purchaseOrderRepository->findById(PurchaseOrderId::fromString($command->purchaseOrderId));
        if (null === $po) {
            throw new PurchaseOrderNotFoundException($command->purchaseOrderId);
        }

        $now = $this->clock->now();

        $po->confirm($now, $now);

        $this->entityManager->wrapInTransaction(function () use ($po): void {
            $this->purchaseOrderRepository->save($po);
        });
        $this->domainEventPublisher->publish($po);
    }
}
