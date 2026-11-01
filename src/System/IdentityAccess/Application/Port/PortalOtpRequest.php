<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Port;

final readonly class PortalOtpRequest
{
    /**
     * @param int    $sendCount the n-th code of the challenge: each one is a new message
     * @param string $purpose   the OtpPurpose value: the SMS wording depends on it
     *                          (sign-in, e-mail change, account deletion)
     */
    public function __construct(
        public string $challengeId,
        public string $phone,
        public string $code,
        public int $sendCount,
        public int $validityMinutes,
        public string $purpose,
    ) {
    }
}
