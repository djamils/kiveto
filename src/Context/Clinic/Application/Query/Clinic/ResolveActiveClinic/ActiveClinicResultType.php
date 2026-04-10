<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Clinic\ResolveActiveClinic;

enum ActiveClinicResultType: string
{
    case NONE     = 'none';
    case SINGLE   = 'single';
    case MULTIPLE = 'multiple';
}
