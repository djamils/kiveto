<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\ValueObject;

/**
 * The document type code (EN16931 BT-3, UNTDID 1001).
 */
enum InvoiceTypeCode: string
{
    case COMMERCIAL_INVOICE = '380';
    case CREDIT_NOTE        = '381';
}
