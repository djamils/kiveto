<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RequestPortalOtp;

/** The same answer whether or not the phone has an account. */
final readonly class OtpChallengeIssued
{
    public function __construct(
        public string $challengeId,
        public \DateTimeImmutable $expiresAt,
        public \DateTimeImmutable $resendAvailableAt,
    ) {
    }
}
