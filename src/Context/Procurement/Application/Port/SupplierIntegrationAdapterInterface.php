<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Port;

use App\Context\Procurement\Application\Dto\CatalogEntryData;
use App\Context\Procurement\Application\Dto\DeliveryNoteData;
use App\Context\Procurement\Application\Dto\SendOrderResult;
use App\Context\Procurement\Domain\PurchaseOrder\PurchaseOrder;
use App\Context\Procurement\Domain\Supplier\Supplier;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierIntegrationMode;
use App\Context\Procurement\Domain\SupplierAccount\SupplierAccount;

interface SupplierIntegrationAdapterInterface
{
    public function identifier(): string;

    public function mode(): SupplierIntegrationMode;

    public function sendOrder(PurchaseOrder $order, SupplierAccount $account): SendOrderResult;

    /**
     * @return list<DeliveryNoteData>
     */
    public function fetchDeliveries(
        Supplier $supplier,
        SupplierAccount $account,
        \DateTimeImmutable $since,
        \DateTimeImmutable $until,
    ): array;

    public function supportsAsyncDeliveryPolling(): bool;

    public function supportsCatalogImport(): bool;

    /**
     * @return list<CatalogEntryData>
     */
    public function fetchCatalog(Supplier $supplier): array;
}
