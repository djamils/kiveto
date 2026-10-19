<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\TaxProfile;

/**
 * What VIES said about the client's VAT number.
 */
enum VatCheckOutcome: string
{
    case UNCHECKED = 'unchecked';
    case VALID     = 'valid';
    case INVALID   = 'invalid';
    // The profile has no VAT number to check.
    case NOT_APPLICABLE = 'not_applicable';
    case UNAVAILABLE    = 'unavailable';

    public function isDefinite(): bool
    {
        return match ($this) {
            self::UNCHECKED, self::UNAVAILABLE => false,
            self::VALID, self::INVALID, self::NOT_APPLICABLE => true,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::UNCHECKED      => 'TVA non vérifiée',
            self::VALID          => 'TVA valide',
            self::INVALID        => 'TVA non reconnue',
            self::NOT_APPLICABLE => 'sans n° de TVA',
            self::UNAVAILABLE    => 'VIES indisponible',
        };
    }
}
