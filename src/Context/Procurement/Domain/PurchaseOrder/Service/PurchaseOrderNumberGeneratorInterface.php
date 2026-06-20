<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\PurchaseOrder\Service;

use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderNumber;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;

interface PurchaseOrderNumberGeneratorInterface
{
    public function next(ClinicId $clinicId, int $year): PurchaseOrderNumber;
}
