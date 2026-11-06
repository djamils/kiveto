<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Port;

/** The absolute links put in the account e-mails (clinic host). */
interface AccountLinkBuilderInterface
{
    public function invitationUrl(string $secret): string;

    public function passwordResetUrl(string $secret): string;

    /**
     * The reset page of the console (backoffice host).
     */
    public function backofficePasswordResetUrl(string $secret): string;

    /** The clinic host's address that opens a support session (one-time secret). */
    public function supportHandoffUrl(string $secret): string;

    /** The console's page where a new member of the Kiveto team chooses a password (backoffice host). */
    public function consoleInvitationUrl(string $secret): string;
}
