<?php

declare(strict_types=1);

namespace App\Context\Consultation\Domain\ValueObject;

enum DiagnosisCertainty: string
{
    case CERTAIN  = 'CERTAIN';
    case PROBABLE = 'PROBABLE';
    case POSSIBLE = 'POSSIBLE';
    case EXCLUDED = 'EXCLUDED';

    public function label(): string
    {
        return match ($this) {
            self::CERTAIN  => 'Certain',
            self::PROBABLE => 'Probable',
            self::POSSIBLE => 'Possible',
            self::EXCLUDED => 'Exclu',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::CERTAIN  => 'Cert.',
            self::PROBABLE => 'Prob.',
            self::POSSIBLE => 'Poss.',
            self::EXCLUDED => 'Exclu',
        };
    }
}
