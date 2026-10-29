<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/** The invitation is still pending but its 7 days are over. */
final class InvitationExpiredException extends \DomainException
{
    public function __construct(string $invitationId)
    {
        parent::__construct(\sprintf('Invitation "%s" has expired.', $invitationId));
    }
}
