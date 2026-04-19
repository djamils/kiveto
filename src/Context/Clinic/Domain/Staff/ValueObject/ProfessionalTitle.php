<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Staff\ValueObject;

enum ProfessionalTitle: string
{
    case DR   = 'DR';
    case PR   = 'PR';
    case NONE = 'NONE';

    public function prefix(): string
    {
        return match ($this) {
            self::DR   => 'Dr. ',
            self::PR   => 'Pr. ',
            self::NONE => '',
        };
    }
}
