<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\ValueObject;

enum Civility: string
{
    case MADAM       = 'madam';
    case MISTER      = 'mister';
    case UNSPECIFIED = 'unspecified';

    public function label(): string
    {
        return match ($this) {
            self::MADAM       => 'Madame',
            self::MISTER      => 'Monsieur',
            self::UNSPECIFIED => 'Non précisée',
        };
    }

    public function honorific(): string
    {
        return match ($this) {
            self::MADAM       => 'Mme',
            self::MISTER      => 'M.',
            self::UNSPECIFIED => '',
        };
    }
}
