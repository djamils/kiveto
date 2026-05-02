<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Domain\ValueObject;

enum StrayCustodyStatus: string
{
    case Active                  = 'active';
    case CancelledOwnerFound     = 'cancelled_owner_found';
    case ClosedHandedToAuthority = 'closed_handed_to_authority';
    case Expired                 = 'expired';
}
