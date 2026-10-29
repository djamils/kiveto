<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Link;

/**
 * `pending` is reserved for link suggestions (slice 7): an accepted
 * invitation confirms a link directly.
 */
enum ClientLinkStatus: string
{
    case PENDING   = 'pending';
    case CONFIRMED = 'confirmed';
    case REVOKED   = 'revoked';
}
