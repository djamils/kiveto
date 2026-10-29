<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Message;

/**
 * Whether an SMS still goes out once the clinic's overage cap is reached:
 * only NON_CRITICAL SMS are capped.
 */
enum Criticality: string
{
    case CRITICAL     = 'CRITICAL';
    case NON_CRITICAL = 'NON_CRITICAL';
}
