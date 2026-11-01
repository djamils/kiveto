<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Support;

use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Domain\Otp\OtpChallenge;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeId;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeRepositoryInterface;
use App\System\IdentityAccess\Domain\Otp\OtpSubjectType;

final class InMemoryOtpChallenges implements OtpChallengeRepositoryInterface
{
    /** @var array<string, OtpChallenge> by id */
    public array $challenges = [];

    /** @var list<string> ids read with a lock, in order */
    public array $locked = [];

    public int $saves = 0;

    public function save(OtpChallenge $challenge): void
    {
        $this->challenges[$challenge->id()->toString()] = $challenge;
        ++$this->saves;
    }

    public function findById(OtpChallengeId $id): ?OtpChallenge
    {
        return $this->challenges[$id->toString()] ?? null;
    }

    public function findByIdForUpdate(OtpChallengeId $id): ?OtpChallenge
    {
        $this->locked[] = $id->toString();

        return $this->findById($id);
    }

    public function findByOnboardingHashForUpdate(string $tokenHash): ?OtpChallenge
    {
        foreach ($this->challenges as $challenge) {
            if ($challenge->onboardingTokenHash() === $tokenHash) {
                $this->locked[] = $challenge->id()->toString();

                return $challenge;
            }
        }

        return null;
    }

    public function revokeOnboardingGrantsForPhone(PhoneNumber $phone, \DateTimeImmutable $at): int
    {
        $count = 0;

        foreach ($this->challenges as $challenge) {
            if ($challenge->phone()->equals($phone) && null !== $challenge->onboardingTokenHash() && null === $challenge->onboardingRevokedAt()) {
                $challenge->revokeOnboarding($at);
                ++$count;
            }
        }

        return $count;
    }

    public function deleteForPhoneOrUser(PhoneNumber $phone, string $userId): int
    {
        $before           = \count($this->challenges);
        $this->challenges = array_filter(
            $this->challenges,
            static fn (OtpChallenge $c): bool => !$c->phone()->equals($phone) && !(OtpSubjectType::USER === $c->subjectType() && $c->subjectRef() === $userId),
        );

        return $before - \count($this->challenges);
    }

    /** The only challenge stored, for the use cases that create one. */
    public function single(): OtpChallenge
    {
        \assert(1 === \count($this->challenges));

        return array_values($this->challenges)[0];
    }
}
