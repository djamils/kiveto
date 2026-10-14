<?php

declare(strict_types=1);

namespace App\Context\Animal\Domain\ValueObject;

/**
 * How the animal died, as recorded when the death is declared.
 */
enum DeathCause: string
{
    case EUTHANASIA = 'euthanasia';
    case NATURAL    = 'natural';
    case ACCIDENT   = 'accident';
    case UNKNOWN    = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::EUTHANASIA => 'Euthanasie',
            self::NATURAL    => 'Mort naturelle',
            self::ACCIDENT   => 'Accident',
            self::UNKNOWN    => 'Circonstances inconnues',
        };
    }
}
