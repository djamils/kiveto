<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Domain\ValueObject;

enum MicrochipRegistryLookupStatus: string
{
    case Pending        = 'pending';
    case FoundInICad    = 'found_in_icad';
    case NotFoundInICad = 'not_found_in_icad';
    case LookupFailed   = 'lookup_failed';
}
