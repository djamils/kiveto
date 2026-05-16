<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

enum ImportSource: string
{
    case ANMV       = 'ANMV';
    case EMA_UPD    = 'EMA_UPD';
    case SWISSMEDIC = 'SWISSMEDIC';
    case MHRA       = 'MHRA';
    case AEMPS      = 'AEMPS';
    case BFARM      = 'BFARM';
}
