<?php

declare(strict_types=1);

namespace App\Context\Consultation\Domain\ValueObject;

enum PlanActionKind: string
{
    case PERFORMED_ACT           = 'PERFORMED_ACT';
    case MEDICATION_PRESCRIPTION = 'MEDICATION_PRESCRIPTION';
    case FOLLOW_UP_APPOINTMENT   = 'FOLLOW_UP_APPOINTMENT';
    case ADVICE                  = 'ADVICE';
    case OTHER                   = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::PERFORMED_ACT           => 'Actes',
            self::MEDICATION_PRESCRIPTION => 'Médicaments',
            self::FOLLOW_UP_APPOINTMENT   => 'RDV',
            self::ADVICE                  => 'Conseils',
            self::OTHER                   => 'Autre',
        };
    }

    public function singularLabel(): string
    {
        return match ($this) {
            self::PERFORMED_ACT           => 'Acte',
            self::MEDICATION_PRESCRIPTION => 'Médicament',
            self::FOLLOW_UP_APPOINTMENT   => 'RDV',
            self::ADVICE                  => 'Conseil',
            self::OTHER                   => 'Autre',
        };
    }

    /**
     * Only performed acts feed the billing draft; medications are billed
     * through their prescription line instead.
     */
    public function isBillable(): bool
    {
        return self::PERFORMED_ACT === $this;
    }
}
