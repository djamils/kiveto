<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\TaxProfile;

/**
 * What the company registry said about the client's SIREN.
 */
enum CompanyCheckOutcome: string
{
    case UNCHECKED = 'unchecked';
    case ACTIVE    = 'active';
    case CEASED    = 'ceased';
    // Non-diffusible company: the SIREN is known but its data is masked.
    case CONFIDENTIAL = 'confidential';
    case NOT_FOUND    = 'not_found';
    case UNAVAILABLE  = 'unavailable';

    /**
     * A definite answer of the registry, kept for the regular re-verification cycle.
     */
    public function isDefinite(): bool
    {
        return match ($this) {
            self::UNCHECKED, self::UNAVAILABLE => false,
            self::ACTIVE, self::CEASED, self::CONFIDENTIAL, self::NOT_FOUND => true,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::UNCHECKED    => 'non vérifiée',
            self::ACTIVE       => 'active',
            self::CEASED       => 'radiée',
            self::CONFIDENTIAL => 'non diffusible',
            self::NOT_FOUND    => 'introuvable',
            self::UNAVAILABLE  => 'registre indisponible',
        };
    }
}
