<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\PurchaseOrder\Repository;

use App\Context\Procurement\Domain\PurchaseOrder\PurchaseOrder;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderNumber;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;

interface PurchaseOrderRepositoryInterface
{
    public function save(PurchaseOrder $order): void;

    public function findById(PurchaseOrderId $id): ?PurchaseOrder;

    public function findByClinicAndNumber(ClinicId $clinicId, PurchaseOrderNumber $number): ?PurchaseOrder;
}
