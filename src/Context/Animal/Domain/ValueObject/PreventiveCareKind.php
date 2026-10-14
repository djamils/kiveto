<?php

declare(strict_types=1);

namespace App\Context\Animal\Domain\ValueObject;

enum PreventiveCareKind: string
{
    case VACCINATION       = 'vaccination';
    case INTERNAL_PARASITE = 'internal_parasite';
    case EXTERNAL_PARASITE = 'external_parasite';
    case OTHER             = 'other';

    public function label(): string
    {
        return match ($this) {
            self::VACCINATION       => 'Vaccination',
            self::INTERNAL_PARASITE => 'Antiparasitaire interne',
            self::EXTERNAL_PARASITE => 'Antiparasitaire externe',
            self::OTHER             => 'Autre acte de prévention',
        };
    }
}
