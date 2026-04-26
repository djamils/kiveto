<?php

declare(strict_types=1);

namespace App\Context\Admission\Domain\ValueObject;

enum PresenterRole: string
{
    case Owner        = 'owner';
    case ThirdParty   = 'third_party';
    case Authority    = 'authority';
    case Association  = 'association';
    case Municipality = 'municipality';
    case Unknown      = 'unknown';
}
