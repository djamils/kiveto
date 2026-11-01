<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\ValueObject;

/**
 * Where a consent change was made: the owner on the Portal, the clinic team
 * (sheet, creation window, counter) or the system (data takeover, demo data).
 */
enum ConsentSource: string
{
    case PORTAL = 'portal';
    case CLINIC = 'clinic';
    case SYSTEM = 'system';
}
