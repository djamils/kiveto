<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

/**
 * Why a client cancelled an appointment (optional, picked on the Portal).
 */
enum CancellationReason: string
{
    case UNAVAILABLE      = 'unavailable';
    case NO_LONGER_NEEDED = 'no_longer_needed';
    case SEEN_ELSEWHERE   = 'seen_elsewhere';
    case OTHER            = 'other';
}
