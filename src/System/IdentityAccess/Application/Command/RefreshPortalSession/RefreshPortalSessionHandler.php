<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RefreshPortalSession;

use App\Shared\Domain\Security\OpaqueTokenHasherInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Service\PortalSessionIssuer;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\Session\RefreshOutcome;
use App\System\IdentityAccess\Domain\Session\RefreshTokenRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The rules, in order: an unknown, revoked or expired token is refused; an
 * account no longer active revokes its family; an unused token is rotated;
 * a token rotated less than 30 s ago, whose successor is unused and that was
 * never re-issued, yields one more pair of the same family; anything else is
 * a replay and revokes the whole family.
 */
#[AsMessageHandler]
final readonly class RefreshPortalSessionHandler
{
    public function __construct(
        private RefreshTokenRepositoryInterface $refreshTokens,
        private UserRepositoryInterface $users,
        private PortalSessionIssuer $sessions,
        private OpaqueTokenHasherInterface $tokenHasher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RefreshPortalSession $command): RefreshResult
    {
        $now   = $this->clock->now();
        $token = $this->refreshTokens->findByHashForUpdate($this->tokenHasher->hash($command->refreshToken));

        if (null === $token) {
            return new RefreshResult(RefreshOutcome::UNKNOWN);
        }

        if ($token->isRevoked()) {
            return new RefreshResult(RefreshOutcome::REVOKED);
        }

        if ($token->isExpired($now)) {
            return new RefreshResult(RefreshOutcome::EXPIRED);
        }

        $user = $this->users->findById(UserId::fromString($token->userId()));
        if (null === $user || UserType::PORTAL !== $user->type() || UserStatus::ACTIVE !== $user->status()) {
            $this->refreshTokens->revokeFamily($token->familyId(), $now);

            return new RefreshResult(RefreshOutcome::USER_INACTIVE);
        }

        if (!$token->isRotated()) {
            return new RefreshResult(RefreshOutcome::ROTATED, $this->sessions->rotate($token, $now));
        }

        $successorId = $token->rotatedToId();
        $successor   = null !== $successorId ? $this->refreshTokens->findById($successorId) : null;

        if ($token->canBeReissued($now, $successor?->isUsed() ?? true)) {
            return new RefreshResult(RefreshOutcome::GRACE_REISSUED, $this->sessions->reissue($token, $now));
        }

        $this->refreshTokens->revokeFamily($token->familyId(), $now);

        return new RefreshResult(RefreshOutcome::REUSE_DETECTED);
    }
}
