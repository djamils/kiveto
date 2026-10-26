<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\ValueObject;

/**
 * The tax status of the buyer, as the Client context qualified it.
 */
enum BuyerTaxStatus: string
{
    case INDIVIDUAL      = 'individual';
    case FRENCH_BUSINESS = 'french_business';
}
