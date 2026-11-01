<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Port;

/**
 * Asks Notification to send the account messages (sender « Kiveto »): the
 * staff e-mails, the one-time codes of the owner Portal and its e-mail
 * change notices. Every display value is resolved by the caller:
 * IdentityAccess never reads a clinic.
 */
interface NotificationRequesterInterface
{
    public function requestStaffInvitation(StaffInvitationRequest $request): void;

    public function requestPasswordReset(PasswordResetRequest $request): void;

    /** The SMS carrying a Portal one-time code; requested last in the transaction. */
    public function requestPortalOtp(PortalOtpRequest $request): void;

    /** A sign-in code resent to the verified e-mail of the phone's account. */
    public function requestPortalOtpByEmail(PortalOtpRequest $request, string $email): void;

    /** The code proving an address an owner wants on their account. */
    public function requestPortalEmailVerification(PortalEmailVerificationRequest $request): void;

    /** The notice to the previous verified address of an owner. */
    public function requestPortalEmailChangedNotice(PortalEmailChangedNoticeRequest $request): void;
}
