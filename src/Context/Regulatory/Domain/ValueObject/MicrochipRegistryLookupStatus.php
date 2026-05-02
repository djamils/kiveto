<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Domain\ValueObject;

enum MicrochipRegistryLookupStatus: string
{
    case Pending            = 'pending';
    case FoundInRegistry    = 'found_in_registry';
    case NotFoundInRegistry = 'not_found_in_registry';
    case LookupFailed       = 'lookup_failed';
}
