<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierReceipt\ValueObject;

enum ReceiptMatchType: string
{
    case AUTO_MATCHED     = 'AUTO_MATCHED';
    case MANUALLY_MATCHED = 'MANUALLY_MATCHED';
    case MANUALLY_CREATED = 'MANUALLY_CREATED';
}
