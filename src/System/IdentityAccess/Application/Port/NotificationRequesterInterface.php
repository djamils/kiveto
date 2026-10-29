<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Port;

/**
 * Asks Notification to send the account messages (sender « Kiveto »): the
 * staff e-mails and the one-time codes of the owner Portal. Every display
 * value is resolved by the caller: IdentityAccess never reads a clinic.
 */
interface NotificationRequesterInterface
{
    public function requestStaffInvitation(StaffInvitationRequest $request): void;

    public function requestPasswordReset(PasswordResetRequest $request): void;

    /** The SMS carrying a Portal one-time code; requested last in the transaction. */
    public function requestPortalOtp(PortalOtpRequest $request): void;
}
