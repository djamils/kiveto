<?php

declare(strict_types=1);

namespace App\Context\Consultation\Domain\ValueObject;

/**
 * Body systems covered by the per-system clinical exam grid (SOAP "O").
 *
 * The icon key maps to the SVG sprite rendered client-side by the cockpit.
 */
enum BodySystem: string
{
    case CARDIOVASCULAR = 'CARDIOVASCULAR';
    case RESPIRATORY    = 'RESPIRATORY';
    case DIGESTIVE      = 'DIGESTIVE';
    case URINARY        = 'URINARY';
    case LOCOMOTOR      = 'LOCOMOTOR';
    case NEUROLOGICAL   = 'NEUROLOGICAL';
    case SKIN           = 'SKIN';
    case OPHTHALMIC     = 'OPHTHALMIC';
    case DENTAL         = 'DENTAL';
    case INTEGUMENT     = 'INTEGUMENT';

    public function label(): string
    {
        return match ($this) {
            self::CARDIOVASCULAR => 'Cardiovasculaire',
            self::RESPIRATORY    => 'Respiratoire',
            self::DIGESTIVE      => 'Digestif',
            self::URINARY        => 'Urinaire',
            self::LOCOMOTOR      => 'Locomoteur',
            self::NEUROLOGICAL   => 'Neurologique',
            self::SKIN           => 'Cutané',
            self::OPHTHALMIC     => 'Ophtalmologique',
            self::DENTAL         => 'Dentaire',
            self::INTEGUMENT     => 'Tégument / Carapace',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::CARDIOVASCULAR => 'Cardio',
            self::RESPIRATORY    => 'Respi',
            self::DIGESTIVE      => 'Digestif',
            self::URINARY        => 'Urinaire',
            self::LOCOMOTOR      => 'Loco',
            self::NEUROLOGICAL   => 'Neuro',
            self::SKIN           => 'Cutané',
            self::OPHTHALMIC     => 'Ophta',
            self::DENTAL         => 'Dentaire',
            self::INTEGUMENT     => 'Tégument',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CARDIOVASCULAR => 'heart',
            self::RESPIRATORY    => 'lungs',
            self::DIGESTIVE      => 'stomach',
            self::URINARY        => 'droplet',
            self::LOCOMOTOR      => 'bone',
            self::NEUROLOGICAL   => 'brain',
            self::SKIN           => 'sparkle',
            self::OPHTHALMIC     => 'eye',
            self::DENTAL         => 'tooth',
            self::INTEGUMENT     => 'shell',
        };
    }

    /**
     * Structured drill-down form rendered for this system, or null when the
     * modal only offers free-text observations.
     */
    public function drilldown(): ?string
    {
        return match ($this) {
            self::CARDIOVASCULAR => 'cardio',
            self::LOCOMOTOR      => 'loco',
            self::SKIN, self::INTEGUMENT => 'derma',
            self::RESPIRATORY, self::DIGESTIVE, self::URINARY,
            self::NEUROLOGICAL, self::OPHTHALMIC, self::DENTAL => null,
        };
    }
}
