<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Domain\ValueObject;

enum MairieNotificationStatus: string
{
    case Pending   = 'pending';
    case Sent      = 'sent';
    case Cancelled = 'cancelled';
}
