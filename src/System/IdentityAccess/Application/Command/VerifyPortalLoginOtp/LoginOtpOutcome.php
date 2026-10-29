<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\VerifyPortalLoginOtp;

final readonly class LoginOtpOutcome
{
    /**
     * @param string|null $userId the active owner account, when authenticated only
     */
    public function __construct(
        public LoginOtpResult $result,
        public ?string $userId = null,
    ) {
    }
}
