<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierReceipt\ValueObject;

enum SupplierReceiptStatus: string
{
    case PENDING_REVIEW = 'PENDING_REVIEW';
    case VALIDATED      = 'VALIDATED';
}
