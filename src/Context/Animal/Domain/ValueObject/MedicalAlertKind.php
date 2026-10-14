<?php

declare(strict_types=1);

namespace App\Context\Animal\Domain\ValueObject;

/**
 * The four columns of the medical synthesis: what the team must know before
 * touching the animal — its active problems, long-term treatments, allergies
 * and behaviour.
 */
enum MedicalAlertKind: string
{
    case ALLERGY             = 'ALLERGY';
    case CHRONIC_CONDITION   = 'CHRONIC_CONDITION';
    case LONG_TERM_TREATMENT = 'LONG_TERM_TREATMENT';
    case BEHAVIOR            = 'BEHAVIOR';

    public function label(): string
    {
        return match ($this) {
            self::ALLERGY             => 'Allergie',
            self::CHRONIC_CONDITION   => 'Suivi',
            self::LONG_TERM_TREATMENT => 'Traitement au long cours',
            self::BEHAVIOR            => 'Comportement',
        };
    }
}
