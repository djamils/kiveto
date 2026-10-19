<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\TaxProfile;

/**
 * A badge derived from the verification. Never blocking: the clinic keeps
 * working with the profile as typed.
 */
enum TaxProfileWarning: string
{
    case UNVERIFIED           = 'unverified';
    case COMPANY_CEASED       = 'company_ceased';
    case COMPANY_NOT_FOUND    = 'company_not_found';
    case COMPANY_CONFIDENTIAL = 'company_confidential';
    case VAT_INVALID          = 'vat_invalid';
    case NAME_MISMATCH        = 'name_mismatch';

    public function label(): string
    {
        return match ($this) {
            self::UNVERIFIED           => 'Vérification en attente',
            self::COMPANY_CEASED       => 'Entreprise radiée selon le registre',
            self::COMPANY_NOT_FOUND    => 'SIREN introuvable dans le registre',
            self::COMPANY_CONFIDENTIAL => 'Entreprise non diffusible au registre',
            self::VAT_INVALID          => 'N° de TVA non reconnu par VIES',
            self::NAME_MISMATCH        => 'Nom différent du registre',
        };
    }

    /**
     * Only the warnings that matter when billing are repeated at the counter.
     */
    public function shownAtCounter(): bool
    {
        return match ($this) {
            self::COMPANY_CEASED, self::COMPANY_NOT_FOUND, self::VAT_INVALID => true,
            self::UNVERIFIED, self::COMPANY_CONFIDENTIAL, self::NAME_MISMATCH => false,
        };
    }
}
