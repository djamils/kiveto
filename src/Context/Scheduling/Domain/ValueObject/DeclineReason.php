<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

/**
 * Why a pending appointment was declined: by the clinic's « Refuser », by
 * expiry (no answer in time) or by a clinic cancel of the pending request.
 */
enum DeclineReason: string
{
    case DECLINED         = 'declined';
    case EXPIRED          = 'expired';
    case CLINIC_CANCELLED = 'clinic_cancelled';
}
