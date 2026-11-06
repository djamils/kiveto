<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Account;

/**
 * The practice software the clinic keeps running alongside Kiveto during
 * the alpha.
 */
enum ParallelSoftware: string
{
    case VETUP = 'vetup';

    case DR_VETO = 'drveto';

    case VETOCOM = 'vetocom';

    case EPIVET = 'epivet';

    case OTHER = 'other';

    case NONE = 'none';

    public function label(): string
    {
        return match ($this) {
            self::VETUP   => 'Vetup',
            self::DR_VETO => 'dr.veto',
            self::VETOCOM => 'Vétocom',
            self::EPIVET  => 'Epivet',
            self::OTHER   => 'Autre',
            self::NONE    => 'Aucun',
        };
    }
}
