<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\ValidateSupplierReceipt;

use App\Context\Procurement\Application\Exception\PurchaseOrderClosedOrCancelledException;
use App\Context\Procurement\Domain\PurchaseOrder\Exception\PurchaseOrderNotFoundException;
use App\Context\Procurement\Domain\PurchaseOrder\PurchaseOrder;
use App\Context\Procurement\Domain\PurchaseOrder\Repository\PurchaseOrderRepositoryInterface;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineStatus;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderStatus;
use App\Context\Procurement\Domain\SupplierReceipt\Event\SupplierReceiptCompletedIntegrationEvent;
use App\Context\Procurement\Domain\SupplierReceipt\Exception\SupplierReceiptNotFoundException;
use App\Context\Procurement\Domain\SupplierReceipt\Repository\SupplierReceiptRepositoryInterface;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\SupplierReceiptId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Application\Event\IntegrationEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ValidateSupplierReceiptHandler
{
    public function __construct(
        private SupplierReceiptRepositoryInterface $receiptRepository,
        private PurchaseOrderRepositoryInterface $purchaseOrderRepository,
        private DomainEventPublisher $domainEventPublisher,
        private IntegrationEventPublisher $integrationEventPublisher,
        private ClockInterface $clock,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(ValidateSupplierReceipt $command): void
    {
        $receipt = $this->receiptRepository->findById(SupplierReceiptId::fromString($command->receiptId));
        if (null === $receipt) {
            throw new SupplierReceiptNotFoundException($command->receiptId);
        }

        $po = $this->purchaseOrderRepository->findById($receipt->purchaseOrderId());
        if (null === $po) {
            throw new PurchaseOrderNotFoundException($receipt->purchaseOrderId()->toString());
        }

        // Guard: PO must be CONFIRMED or PARTIALLY_RECEIVED to accept goods
        $allowedStatuses = [PurchaseOrderStatus::CONFIRMED, PurchaseOrderStatus::PARTIALLY_RECEIVED];
        if (!\in_array($po->status(), $allowedStatuses, true)) {
            throw new PurchaseOrderClosedOrCancelledException($po->id()->toString(), $po->status()->value);
        }

        $now = $this->clock->now();

        // Build map of lineId => receivedAmount from the receipt lines
        /** @var array<string, numeric-string> $receivedByLineId */
        $receivedByLineId = [];
        foreach ($receipt->lines() as $line) {
            $amount = $line->receivedAmount();
            if (!is_numeric($amount)) {
                throw new \UnexpectedValueException(\sprintf('Line "%s" has non-numeric receivedAmount "%s".', $line->purchaseOrderLineId()->toString(), $amount));
            }
            $receivedByLineId[$line->purchaseOrderLineId()->toString()] = $amount;
        }

        $isFullReception = $this->isFullReception($po, $receivedByLineId);

        $this->entityManager->wrapInTransaction(function () use ($receipt, $po, $receivedByLineId, $isFullReception, $now): void {
            $receipt->validate($now, $now);

            if ($isFullReception) {
                $po->recordFullReception($receivedByLineId, $now);
            } else {
                $po->recordPartialReception($receivedByLineId, $now);
            }

            $this->receiptRepository->save($receipt);
            $this->purchaseOrderRepository->save($po);
        });

        // Publish domain events for both aggregates (single-arg contract)
        $this->domainEventPublisher->publish($receipt);
        $this->domainEventPublisher->publish($po);

        // Build lines payload for integration event
        $lines = array_map(function ($line) {
            $lot   = $line->lotInformation();
            $price = $line->actualUnitPrice();

            return [
                'purchaseOrderLineId'     => $line->purchaseOrderLineId()->toString(),
                'articleId'               => $line->articleId()->toString(),
                'receivedAmount'          => $line->receivedAmount(),
                'receivedUnit'            => $line->receivedUnit()->toString(),
                'lotNumber'               => $lot?->lotNumber,
                'expiryDate'              => $lot?->expiryDate->format(\DateTimeInterface::ATOM),
                'manufacturedAt'          => $lot?->manufacturedAt?->format(\DateTimeInterface::ATOM),
                'actualUnitPriceMinor'    => $price?->minorUnits(),
                'actualUnitPriceCurrency' => $price?->currency()->toString(),
            ];
        }, $receipt->lines());

        // Publish integration event to notify Inventory BC
        $this->integrationEventPublisher->publish(new SupplierReceiptCompletedIntegrationEvent(
            receiptId: $receipt->id()->toString(),
            clinicId: $receipt->clinicId()->toString(),
            supplierId: $receipt->supplierId()->toString(),
            purchaseOrderId: $receipt->purchaseOrderId()->toString(),
            lines: $lines,
        ));
    }

    /**
     * Determines whether all active PO lines will be fully satisfied after this reception.
     *
     * @param array<string, numeric-string> $receivedByLineId
     */
    private function isFullReception(PurchaseOrder $po, array $receivedByLineId): bool
    {
        foreach ($po->lines() as $line) {
            if (PurchaseOrderLineStatus::ACTIVE !== $line->status()) {
                continue;
            }

            $lineId          = $line->id()->toString();
            $alreadyReceived = $line->receivedAmount();
            $orderedAmount   = $line->orderedAmount();
            $incomingAmount  = $receivedByLineId[$lineId] ?? '0';

            if (!is_numeric($alreadyReceived) || !is_numeric($orderedAmount)) {
                throw new \UnexpectedValueException('PO line has non-numeric amount.');
            }

            /** @var numeric-string $alreadyReceived */
            /** @var numeric-string $orderedAmount */
            $newReceived = bcadd($alreadyReceived, $incomingAmount, 6);
            if (bccomp($newReceived, $orderedAmount, 6) < 0) {
                // At least one active line still has remaining quantity
                return false;
            }
        }

        return true;
    }
}
