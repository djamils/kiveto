<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Account;

/**
 * What the clinic pays Kiveto: nothing during the pilot (switched to paid by
 * hand), 99 € HT per veterinarian and per month once paid, nothing ever for
 * an internal (demo) clinic.
 */
enum ClinicPlan: string
{
    case PILOT = 'pilot';

    case PAID = 'paid';

    case INTERNAL = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::PILOT    => 'Pilote (gratuit)',
            self::PAID     => 'Payant',
            self::INTERNAL => 'Interne (non facturée)',
        };
    }

    public function isBilled(): bool
    {
        return self::PAID === $this;
    }
}
