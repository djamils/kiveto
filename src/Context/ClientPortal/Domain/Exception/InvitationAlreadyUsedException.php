<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Exception;

/** The invitation was accepted or revoked: it cannot be used any more. */
final class InvitationAlreadyUsedException extends \DomainException
{
    public function __construct(string $invitationId)
    {
        parent::__construct(\sprintf('Invitation "%s" is no longer pending.', $invitationId));
    }
}
