<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Clinic\CheckClinicSlug;

enum ClinicSlugCheck: string
{
    case AVAILABLE = 'available';

    case TAKEN = 'taken';

    /** Lower-case letters, digits and dashes only. */
    case INVALID = 'invalid';
}
