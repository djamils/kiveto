<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\VerifyPortalActionOtp;

use App\System\IdentityAccess\Application\Service\ActionOtpVerifier;
use App\System\IdentityAccess\Domain\Otp\OtpPurpose;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class VerifyPortalActionOtpHandler
{
    public function __construct(private ActionOtpVerifier $verifier)
    {
    }

    public function __invoke(VerifyPortalActionOtp $command): ActionOtpResult
    {
        $purpose = OtpPurpose::from($command->purpose);

        if (!$purpose->isAction()) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not an action confirmed by an SMS code.', $purpose->value));
        }

        return $this->verifier->verify($command->userId, $command->challengeId, $command->code, $purpose)->result;
    }
}
