<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\ValueObject;

/**
 * Whether the client is subject to VAT in France (« assujetti »). Having a
 * SIREN is not enough: a non-VAT-liable association stays INDIVIDUAL.
 */
enum ClientTaxStatus: string
{
    case INDIVIDUAL      = 'individual';
    case FRENCH_BUSINESS = 'french_business';

    public function label(): string
    {
        return match ($this) {
            self::INDIVIDUAL      => 'Particulier ou structure non assujettie',
            self::FRENCH_BUSINESS => 'Professionnel assujetti à la TVA',
        };
    }
}
