<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Port;

/**
 * Asks Notification to send the account e-mails (sender « Kiveto »). Every
 * display value is resolved by the caller: IdentityAccess never reads a clinic.
 */
interface NotificationRequesterInterface
{
    public function requestStaffInvitation(StaffInvitationRequest $request): void;

    public function requestPasswordReset(PasswordResetRequest $request): void;
}
