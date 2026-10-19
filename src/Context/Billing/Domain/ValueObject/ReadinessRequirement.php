<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\ValueObject;

/**
 * Something a professional invoice needs before it can be numbered and later
 * transmitted as an electronic invoice.
 */
enum ReadinessRequirement: string
{
    case BUYER_LEGAL_NAME            = 'buyer_legal_name';
    case BUYER_SIREN                 = 'buyer_siren';
    case BUYER_ADDRESS               = 'buyer_address';
    case LINE_TAX_CATEGORY           = 'line_tax_category';
    case LINE_ZERO_RATE              = 'line_zero_rate';
    case SELLER_SIREN                = 'seller_siren';
    case SELLER_VAT                  = 'seller_vat';
    case SELLER_ADDRESS              = 'seller_address';
    case SELLER_LEGAL_NAME           = 'seller_legal_name';
    case SELLER_NUMBERING_NOT_UNIQUE = 'seller_numbering_not_unique';
}
