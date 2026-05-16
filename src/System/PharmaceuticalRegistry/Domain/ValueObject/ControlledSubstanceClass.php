<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

enum ControlledSubstanceClass: string
{
    case NARCOTIC_I       = 'NARCOTIC_I';
    case NARCOTIC_II      = 'NARCOTIC_II';
    case PSYCHOTROPIC     = 'PSYCHOTROPIC';
    case ANABOLIC         = 'ANABOLIC';
    case OTHER_CONTROLLED = 'OTHER_CONTROLLED';
}
