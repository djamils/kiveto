<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Domain;

/**
 * Where a feature stands for Kiveto. An upcoming one is listed, greyed, and
 * cannot be switched on anywhere: its module does not exist yet.
 */
enum FeatureStatus: string
{
    case AVAILABLE = 'available';

    case BETA = 'beta';

    case INTERNAL = 'internal';

    case UPCOMING = 'upcoming';

    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Disponible',
            self::BETA      => 'Bêta',
            self::INTERNAL  => 'Interne',
            self::UPCOMING  => 'À venir',
        };
    }
}
