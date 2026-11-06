<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Application\Service;

/**
 * Whether a clinic has a feature, and what decided it (FeatureResolver).
 */
enum FeatureDecision: string
{
    /** « À venir »: its module does not exist yet. */
    case UPCOMING = 'upcoming';

    /** Cut off everywhere (the kill switch). */
    case CUT_OFF = 'cut_off';

    /** Kept for a plan the clinic is not on. */
    case OUTSIDE_AUDIENCE = 'outside_audience';

    /** Switched on for the clinic (console, starting features). */
    case SET_ON = 'set_on';

    /** Switched off for the clinic. */
    case SET_OFF = 'set_off';

    /** Never set: the clinic keeps the state it had before the catalogue. */
    case LEGACY_ON = 'legacy_on';

    case LEGACY_OFF = 'legacy_off';

    public function isOn(): bool
    {
        return self::SET_ON === $this || self::LEGACY_ON === $this;
    }

    /** Whether what was set for the clinic decides (and its switch can change it). */
    public function isTheClinicsOwn(): bool
    {
        return !\in_array($this, [self::UPCOMING, self::CUT_OFF, self::OUTSIDE_AUDIENCE], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::UPCOMING         => 'À venir',
            self::CUT_OFF          => 'Coupée partout',
            self::OUTSIDE_AUDIENCE => 'Hors formule',
            self::SET_ON           => 'Activée',
            self::SET_OFF          => 'Désactivée',
            self::LEGACY_ON        => 'Activée (état d’origine)',
            self::LEGACY_OFF       => 'Désactivée (état d’origine)',
        };
    }
}
