<?php

declare(strict_types=1);

namespace App\Context\Consultation\Domain\ValueObject;

enum ExamStatus: string
{
    case NORMAL   = 'NORMAL';
    case ANOMALY  = 'ANOMALY';
    case UNTESTED = 'UNTESTED';

    public function label(): string
    {
        return match ($this) {
            self::NORMAL   => 'RAS',
            self::ANOMALY  => 'Anomalie',
            self::UNTESTED => 'Non testé',
        };
    }
}
