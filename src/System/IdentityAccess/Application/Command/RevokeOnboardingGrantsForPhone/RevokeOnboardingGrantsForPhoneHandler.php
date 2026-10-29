<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RevokeOnboardingGrantsForPhone;

use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RevokeOnboardingGrantsForPhoneHandler
{
    public function __construct(
        private OtpChallengeRepositoryInterface $challenges,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RevokeOnboardingGrantsForPhone $command): int
    {
        return $this->challenges->revokeOnboardingGrantsForPhone(PhoneNumber::fromString($command->phone), $this->clock->now());
    }
}
