<?php

declare(strict_types=1);

namespace App\System\Money\Domain\ValueObject;

enum PsychologicalStrategy: string
{
    case NINETY_NINE = 'ninety_nine';
    case NINETY      = 'ninety';
    case TEN_CENTS   = 'ten_cents';
}
