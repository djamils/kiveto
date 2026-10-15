<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\ValueObject;

/**
 * The VAT regime the clinic files under: it sets the rhythm of its sales
 * reporting to the tax administration.
 */
enum VatRegime: string
{
    case MONTHLY    = 'MONTHLY';
    case QUARTERLY  = 'QUARTERLY';
    case SIMPLIFIED = 'SIMPLIFIED';
}
