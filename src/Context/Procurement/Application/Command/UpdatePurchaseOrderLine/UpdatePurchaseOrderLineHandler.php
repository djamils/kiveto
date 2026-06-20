<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\UpdatePurchaseOrderLine;

use App\Context\Procurement\Domain\PurchaseOrder\Exception\PurchaseOrderNotFoundException;
use App\Context\Procurement\Domain\PurchaseOrder\Repository\PurchaseOrderRepositoryInterface;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdatePurchaseOrderLineHandler
{
    public function __construct(
        private PurchaseOrderRepositoryInterface $purchaseOrderRepository,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(UpdatePurchaseOrderLine $command): void
    {
        $po = $this->purchaseOrderRepository->findById(PurchaseOrderId::fromString($command->purchaseOrderId));
        if (null === $po) {
            throw new PurchaseOrderNotFoundException($command->purchaseOrderId);
        }

        $now = $this->clock->now();

        if (!is_numeric($command->orderedAmount)) {
            throw new \InvalidArgumentException(\sprintf('orderedAmount "%s" is not numeric.', $command->orderedAmount));
        }

        /** @var numeric-string $orderedAmount */
        $orderedAmount = $command->orderedAmount;

        $po->updateLine(
            lineId: PurchaseOrderLineId::fromString($command->lineId),
            orderedAmount: $orderedAmount,
            unitPrice: Money::fromMinorUnits($command->unitPriceMinor, CurrencyCode::fromString($command->unitPriceCurrency)),
            note: $command->note,
            updatedAt: $now,
        );

        $this->entityManager->wrapInTransaction(function () use ($po): void {
            $this->purchaseOrderRepository->save($po);
        });
        $this->domainEventPublisher->publish($po);
    }
}
