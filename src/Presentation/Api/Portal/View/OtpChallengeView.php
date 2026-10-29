<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;
use App\Presentation\Api\Portal\Support\ApiDate;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\OtpChallengeIssued;

/** Answer of the code-sending routes, identical whether the phone has an account or not. */
final readonly class OtpChallengeView
{
    public function __construct(
        public string $challengeId,
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date-time'])]
        public string $expiresAt,
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date-time'])]
        public string $resendAvailableAt,
    ) {
    }

    public static function of(OtpChallengeIssued $issued): self
    {
        return new self($issued->challengeId, ApiDate::format($issued->expiresAt), ApiDate::format($issued->resendAvailableAt));
    }
}
