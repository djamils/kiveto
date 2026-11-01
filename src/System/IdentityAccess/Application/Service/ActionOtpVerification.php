<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Service;

use App\System\IdentityAccess\Application\Command\VerifyPortalActionOtp\ActionOtpResult;
use App\System\IdentityAccess\Domain\Otp\OtpChallenge;

/** The result, and the challenge it consumed when the code was right. */
final readonly class ActionOtpVerification
{
    public function __construct(
        public ActionOtpResult $result,
        public ?OtpChallenge $challenge,
    ) {
    }
}
