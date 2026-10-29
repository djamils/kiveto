<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetPortalInvitationByToken;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The invitation behind a link token, as the owner sees it before
 * accepting. Answers PortalInvitationDetails; InvitationInvalidException
 * (unknown or replaced token), InvitationExpiredException,
 * InvitationAlreadyUsedException (accepted or revoked).
 */
final readonly class GetPortalInvitationByToken implements QueryInterface
{
    public function __construct(
        #[\SensitiveParameter]
        public string $token,
    ) {
    }
}
