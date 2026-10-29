<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Invitation;

/**
 * Stored statuses; « expired » is derived from a pending invitation's
 * expiry date, never stored.
 */
enum PortalInvitationStatus: string
{
    case PENDING  = 'pending';
    case ACCEPTED = 'accepted';
    case REVOKED  = 'revoked';
}
