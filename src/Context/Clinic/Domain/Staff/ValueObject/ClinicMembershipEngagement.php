<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Staff\ValueObject;

/**
 * The contract a member works under. It does not change what they may do; it
 * tells the team how long they are here for.
 */
enum ClinicMembershipEngagement: string
{
    case PARTNER     = 'PARTNER';
    case EMPLOYEE    = 'EMPLOYEE';
    case FIXED_TERM  = 'FIXED_TERM';
    case CONTRACTOR  = 'CONTRACTOR';
    case REPLACEMENT = 'REPLACEMENT';
    case INTERN      = 'INTERN';

    /** A contract that ends by itself, so the access should end with it. */
    public function isTemporary(): bool
    {
        return match ($this) {
            self::FIXED_TERM, self::REPLACEMENT, self::INTERN => true,
            default => false,
        };
    }
}
