<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

interface InvitationLinkBuilderInterface
{
    /** The public link of an invitation; the token travels in the URL fragment. */
    public function invitationLink(#[\SensitiveParameter] string $token): string;
}
