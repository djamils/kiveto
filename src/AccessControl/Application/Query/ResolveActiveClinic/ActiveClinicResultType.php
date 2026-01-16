<?php

declare(strict_types=1);

namespace App\AccessControl\Application\Query\ResolveActiveClinic;

enum ActiveClinicResultType: string
{
    case NONE     = 'none';
    case SINGLE   = 'single';
    case MULTIPLE = 'multiple';
}
