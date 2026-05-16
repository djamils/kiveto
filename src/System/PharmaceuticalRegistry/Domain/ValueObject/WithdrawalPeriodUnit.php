<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

enum WithdrawalPeriodUnit: string
{
    case DAY  = 'DAY';
    case HOUR = 'HOUR';
}
