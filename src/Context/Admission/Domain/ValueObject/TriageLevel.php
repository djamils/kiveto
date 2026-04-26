<?php

declare(strict_types=1);

namespace App\Context\Admission\Domain\ValueObject;

enum TriageLevel: string
{
    case Standard       = 'standard';
    case Priority       = 'priority';
    case Emergency      = 'emergency';
    case VitalEmergency = 'vital_emergency';

    public function ordinal(): int
    {
        return match ($this) {
            self::Standard       => 0,
            self::Priority       => 1,
            self::Emergency      => 2,
            self::VitalEmergency => 3,
        };
    }
}
