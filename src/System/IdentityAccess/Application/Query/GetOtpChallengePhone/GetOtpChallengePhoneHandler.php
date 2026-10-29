<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\GetOtpChallengePhone;

use App\System\IdentityAccess\Domain\Otp\OtpChallengeId;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class GetOtpChallengePhoneHandler
{
    public function __construct(private OtpChallengeRepositoryInterface $challenges)
    {
    }

    public function __invoke(GetOtpChallengePhone $query): ?string
    {
        if (!Uuid::isValid($query->challengeId)) {
            return null;
        }

        return $this->challenges->findById(OtpChallengeId::fromString($query->challengeId))?->phone()->toString();
    }
}
