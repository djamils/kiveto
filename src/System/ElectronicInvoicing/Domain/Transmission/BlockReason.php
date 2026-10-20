<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Transmission;

enum BlockReason: string
{
    case RENDERING_FAILED              = 'RENDERING_FAILED';
    case ORGANISATION_MISMATCH         = 'ORGANISATION_MISMATCH';
    case PLATFORM_REFUSED              = 'PLATFORM_REFUSED';
    case SUBMISSION_ATTEMPTS_EXHAUSTED = 'SUBMISSION_ATTEMPTS_EXHAUSTED';
    case DUPLICATE_FLOW                = 'DUPLICATE_FLOW';
}
