<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Domain;

/**
 * Which clinics a feature is kept for (F2 « Réservée à »): every clinic, or
 * those on one plan. A clinic outside its audience never has it, whatever
 * was set for it.
 */
enum FeatureAudience: string
{
    case ALL = 'all';

    case PILOT = 'pilot';

    case PAID = 'paid';

    /** The plan of a clinic that has no account yet. */
    public const string DEFAULT_PLAN = 'pilot';

    public function label(): string
    {
        return match ($this) {
            self::ALL   => 'Toutes les cliniques',
            self::PILOT => 'Cliniques pilotes',
            self::PAID  => 'Cliniques payantes',
        };
    }

    /** Whether a clinic on this plan ('pilot', 'paid'; null: no account yet) may have the feature. */
    public function admits(?string $plan): bool
    {
        return self::ALL === $this || $this->value === ($plan ?? self::DEFAULT_PLAN);
    }
}
