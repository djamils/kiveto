<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\MatchManualDelivery;

use App\Context\Procurement\Application\Command\CreateSupplierReceipt\CreateSupplierReceipt;
use App\Context\Procurement\Application\Exception\ClinicSupplierMismatchException;
use App\Context\Procurement\Application\Exception\UnmatchedDeliveryAlreadyResolvedException;
use App\Context\Procurement\Application\Exception\UnmatchedDeliveryNotFoundException;
use App\Context\Procurement\Domain\PurchaseOrder\Exception\InvalidPurchaseOrderStatusTransitionException;
use App\Context\Procurement\Domain\PurchaseOrder\Exception\PurchaseOrderNotFoundException;
use App\Context\Procurement\Domain\PurchaseOrder\Repository\PurchaseOrderRepositoryInterface;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderStatus;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\ReceiptMatchType;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class MatchManualDeliveryHandler
{
    public function __construct(
        private PurchaseOrderRepositoryInterface $purchaseOrderRepository,
        private CommandBusInterface $commandBus,
        private Connection $connection,
    ) {
    }

    public function __invoke(MatchManualDelivery $command): void
    {
        $unmatchedId = Uuid::fromString($command->unmatchedDeliveryId)->toBinary();

        // Step 1: Load the unmatched delivery row via DBAL
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM procurement__unmatched_deliveries WHERE id = :id',
            ['id' => $unmatchedId],
        );
        if (false === $row) {
            throw new UnmatchedDeliveryNotFoundException($command->unmatchedDeliveryId);
        }

        // Step 2: Assert the delivery has not already been resolved
        if (null !== $row['resolved_at']) {
            throw new UnmatchedDeliveryAlreadyResolvedException($command->unmatchedDeliveryId);
        }

        // Step 3: Load the target PurchaseOrder
        $po = $this->purchaseOrderRepository->findById(PurchaseOrderId::fromString($command->purchaseOrderId));
        if (null === $po) {
            throw new PurchaseOrderNotFoundException($command->purchaseOrderId);
        }

        // Step 4: Assert that clinic and supplier match between PO and unmatched delivery
        $deliverySupplierId = Uuid::fromBinary(RowAccessor::string($row, 'supplier_id'))->toString();
        if ($po->clinicId()->toString() !== $command->clinicId || $po->supplierId()->toString() !== $deliverySupplierId) {
            throw new ClinicSupplierMismatchException();
        }

        // Step 5: Assert PO is in a state that can receive goods
        $allowed = [PurchaseOrderStatus::CONFIRMED, PurchaseOrderStatus::PARTIALLY_RECEIVED];
        if (!\in_array($po->status(), $allowed, true)) {
            throw new InvalidPurchaseOrderStatusTransitionException(
                \sprintf('PO "%s" is in status %s.', $command->purchaseOrderId, $po->status()->value)
            );
        }

        // Step 6: Dispatch CreateSupplierReceipt command
        $dnr     = RowAccessor::string($row, 'delivery_note_reference');
        $decoded = json_decode(RowAccessor::string($row, 'raw_payload_json'), true, 512, \JSON_THROW_ON_ERROR);

        /**
         * @var list<array{purchaseOrderLineId: string, articleId: string, receivedAmount: string, receivedUnit: string, lotNumber: string|null, expiryDate: string|null, manufacturedAt: string|null, actualUnitPriceMinor: int|null, actualUnitPriceCurrency: string|null, note: string|null}> $lines
         */
        $lines = \is_array($decoded) ? array_values($decoded) : [];

        $this->commandBus->dispatch(new CreateSupplierReceipt(
            clinicId: $command->clinicId,
            supplierId: $po->supplierId()->toString(),
            purchaseOrderId: $command->purchaseOrderId,
            deliveryNoteReference: $dnr,
            matchType: ReceiptMatchType::MANUALLY_MATCHED->value,
            receivedBy: $command->matchedBy,
            comment: null,
            lines: $lines,
        ));

        // Step 7: Mark the unmatched delivery as resolved
        $now        = new \DateTimeImmutable();
        $resolvedBy = null !== $command->matchedBy ? Uuid::fromString($command->matchedBy)->toBinary() : null;
        $this->connection->executeStatement(
            'UPDATE procurement__unmatched_deliveries SET resolved_at = :resolvedAt, resolved_by = :resolvedBy WHERE id = :id',
            ['resolvedAt' => $now->format('Y-m-d H:i:s'), 'resolvedBy' => $resolvedBy, 'id' => $unmatchedId],
        );
    }
}
