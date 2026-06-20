<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Service;

use App\Context\Procurement\Application\Dto\DeliveryNoteData;
use App\Context\Procurement\Application\Port\PurchaseOrderReadRepositoryInterface;
use App\Context\Procurement\Application\Port\SupplierReceiptReadRepositoryInterface;
use App\Context\Procurement\Domain\Supplier\Supplier;
use App\Context\Procurement\Domain\SupplierAccount\SupplierAccount;
use App\Shared\Application\Bus\CommandBusInterface;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class IncomingDeliveryProcessor
{
    public function __construct(
        /** @phpstan-ignore property.onlyWritten */
        private CommandBusInterface $commandBus,
        private PurchaseOrderReadRepositoryInterface $purchaseOrderReadRepository,
        private SupplierReceiptReadRepositoryInterface $supplierReceiptReadRepository,
        private Connection $connection,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<DeliveryNoteData> $deliveries
     */
    public function process(Supplier $supplier, SupplierAccount $account, array $deliveries): void
    {
        foreach ($deliveries as $delivery) {
            try {
                $this->processDelivery($supplier, $account, $delivery);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to process delivery', [
                    'deliveryNoteReference' => $delivery->deliveryNoteReference,
                    'supplier'              => $supplier->id()->toString(),
                    'error'                 => $e->getMessage(),
                ]);
            }
        }
    }

    private function processDelivery(Supplier $supplier, SupplierAccount $account, DeliveryNoteData $delivery): void
    {
        // Idempotence check: skip if a receipt for this delivery note already exists.
        $alreadyProcessed = $this->supplierReceiptReadRepository->existsBySupplierAndDeliveryNote(
            $supplier->id()->toString(),
            $delivery->deliveryNoteReference,
        );

        if ($alreadyProcessed) {
            $this->logger->info('Skipping already processed delivery', [
                'deliveryNoteReference' => $delivery->deliveryNoteReference,
            ]);

            return;
        }

        // Try to match to an open PO by external reference.
        $poRow = $this->purchaseOrderReadRepository->findByExternalReference(
            $delivery->purchaseOrderExternalReference,
        );

        if (null !== $poRow) {
            // TODO: dispatch CreateSupplierReceipt command when T10 commands are implemented.
            $this->logger->info('Matched delivery to purchase order', [
                'deliveryNoteReference' => $delivery->deliveryNoteReference,
                'purchaseOrderId'       => $poRow['id'] ?? 'unknown',
            ]);
        } else {
            // Insert into unmatched deliveries table for manual resolution.
            $this->connection->executeStatement(
                'INSERT INTO procurement__unmatched_deliveries (id, clinic_id, supplier_id, delivery_note_reference, raw_payload_json, received_at)
                 VALUES (:id, :clinicId, :supplierId, :dnr, :payload, :receivedAt)',
                [
                    'id'         => Uuid::v7()->toBinary(),
                    'clinicId'   => Uuid::fromString($account->clinicId()->toString())->toBinary(),
                    'supplierId' => Uuid::fromString($supplier->id()->toString())->toBinary(),
                    'dnr'        => $delivery->deliveryNoteReference,
                    'payload'    => json_encode($delivery->lines),
                    'receivedAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                ],
            );

            $this->logger->info('Stored unmatched delivery', [
                'deliveryNoteReference' => $delivery->deliveryNoteReference,
            ]);
        }
    }
}
