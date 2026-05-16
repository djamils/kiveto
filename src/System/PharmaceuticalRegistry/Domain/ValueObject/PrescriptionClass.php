<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

enum PrescriptionClass: string
{
    case NONE          = 'NONE';
    case RX            = 'RX';
    case RX_CONTROLLED = 'RX_CONTROLLED';
    case RX_NARCOTIC   = 'RX_NARCOTIC';
}
