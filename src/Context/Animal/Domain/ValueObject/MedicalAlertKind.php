<?php

declare(strict_types=1);

namespace App\Context\Animal\Domain\ValueObject;

enum MedicalAlertKind: string
{
    case ALLERGY           = 'ALLERGY';
    case CHRONIC_CONDITION = 'CHRONIC_CONDITION';

    public function label(): string
    {
        return match ($this) {
            self::ALLERGY           => 'Allergie',
            self::CHRONIC_CONDITION => 'Suivi',
        };
    }
}
