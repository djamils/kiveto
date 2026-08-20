<?php

declare(strict_types=1);

namespace App\Context\Consultation\Domain\ValueObject;

enum BillingLineSource: string
{
    case PLAN_ACT     = 'PLAN_ACT';
    case PRESCRIPTION = 'PRESCRIPTION';

    public function label(): string
    {
        return match ($this) {
            self::PLAN_ACT     => 'Acte',
            self::PRESCRIPTION => 'Médicament',
        };
    }
}
