<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Connection;

enum OnboardingState: string
{
    case PENDING  = 'pending';
    case VERIFIED = 'verified';
    case REJECTED = 'rejected';
}
