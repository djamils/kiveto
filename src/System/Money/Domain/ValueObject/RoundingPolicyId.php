<?php

declare(strict_types=1);

namespace App\System\Money\Domain\ValueObject;

enum RoundingPolicyId: string
{
    case ACCOUNTING    = 'accounting';
    case COMMERCIAL    = 'commercial';
    case SWISS_CASH    = 'swiss_cash';
    case PSYCHOLOGICAL = 'psychological';
}
