<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Adapter\Supplier\Manual;

use App\Context\Procurement\Application\Dto\CatalogEntryData;
use App\Context\Procurement\Application\Dto\DeliveryNoteData;
use App\Context\Procurement\Application\Dto\SendOrderResult;
use App\Context\Procurement\Application\Port\SupplierIntegrationAdapterInterface;
use App\Context\Procurement\Domain\PurchaseOrder\PurchaseOrder;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\ExternalReference;
use App\Context\Procurement\Domain\Supplier\Supplier;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierIntegrationMode;
use App\Context\Procurement\Domain\SupplierAccount\SupplierAccount;
use App\Shared\Domain\Storage\FileStorageInterface;

/**
 * Abstract base for adapters that export a purchase order to a file (CSV, PDF, etc.)
 * and store it via FileStorage. Subclasses define the export format and filename.
 *
 * Manual export adapters do NOT support delivery polling or catalog import.
 */
abstract class ManualExportAdapter implements SupplierIntegrationAdapterInterface
{
    public function __construct(protected readonly FileStorageInterface $fileStorage)
    {
    }

    public function mode(): SupplierIntegrationMode
    {
        return SupplierIntegrationMode::MANUAL_EXPORT;
    }

    public function sendOrder(PurchaseOrder $order, SupplierAccount $account): SendOrderResult
    {
        $content     = $this->buildExportContent($order, $account);
        $filename    = $this->exportFilename($order);
        $fileId      = $this->fileStorage->store($filename, $content);
        $trackingUrl = $this->fileStorage->getUrl($fileId);

        return SendOrderResult::success(
            ref: ExternalReference::create($fileId, $this->identifier()),
            sentAt: new \DateTimeImmutable(),
            trackingUrl: $trackingUrl,
        );
    }

    /**
     * @return list<DeliveryNoteData>
     */
    public function fetchDeliveries(
        Supplier $supplier,
        SupplierAccount $account,
        \DateTimeImmutable $since,
        \DateTimeImmutable $until,
    ): array {
        return [];
    }

    public function supportsAsyncDeliveryPolling(): bool
    {
        return false;
    }

    public function supportsCatalogImport(): bool
    {
        return false;
    }

    /**
     * @return list<CatalogEntryData>
     */
    public function fetchCatalog(Supplier $supplier): array
    {
        return [];
    }

    /**
     * Build the file content from the purchase order.
     */
    abstract protected function buildExportContent(PurchaseOrder $order, SupplierAccount $account): string;

    /**
     * Return the target filename for the exported file.
     */
    abstract protected function exportFilename(PurchaseOrder $order): string;
}
