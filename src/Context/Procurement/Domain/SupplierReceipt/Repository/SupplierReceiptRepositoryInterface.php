<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierReceipt\Repository;

use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierReceipt\SupplierReceipt;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\DeliveryNoteReference;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\SupplierReceiptId;

interface SupplierReceiptRepositoryInterface
{
    public function save(SupplierReceipt $receipt): void;

    public function findById(SupplierReceiptId $id): ?SupplierReceipt;

    public function findByDeliveryNoteReference(SupplierId $supplierId, DeliveryNoteReference $ref): ?SupplierReceipt;
}
