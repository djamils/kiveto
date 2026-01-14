<?php

declare(strict_types=1);

namespace App\AccessControl\Application\Query\ResolveClinicSelectionForUser;

enum ClinicSelectionType: string
{
    case NONE     = 'none';
    case SINGLE   = 'single';
    case MULTIPLE = 'multiple';
}
