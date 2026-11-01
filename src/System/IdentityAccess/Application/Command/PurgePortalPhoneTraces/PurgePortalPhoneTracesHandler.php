<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\PurgePortalPhoneTraces;

use App\Shared\Domain\Idempotency\IdempotencyStoreInterface;
use App\System\IdentityAccess\Application\Port\PortalOtpQuotasInterface;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeRepositoryInterface;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The phone is read first, then locked, then the account: the order of the
 * claims of a phone (ClaimPortalIdentity), so the two never deadlock.
 */
#[AsMessageHandler]
final readonly class PurgePortalPhoneTracesHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private OtpChallengeRepositoryInterface $challenges,
        private PortalOtpQuotasInterface $quotas,
        private IdempotencyStoreInterface $idempotency,
    ) {
    }

    /**
     * @throws \LogicException the account is not a disabled owner account
     */
    public function __invoke(PurgePortalPhoneTraces $command): PurgedPortalIdentity
    {
        $id    = UserId::fromString($command->userId);
        $phone = $this->users->findById($id)?->phone();

        if (null !== $phone) {
            $this->users->lockPortalPhone($phone);
        }

        $user = self::disabledOwner($this->users->findByIdForUpdate($id));

        if (null !== $phone) {
            $this->challenges->deleteForPhoneOrUser($phone, $command->userId);
            $this->quotas->forgetPhone($phone->toString());
        }

        $this->idempotency->forgetPrincipal($command->userId);

        return new PurgedPortalIdentity($phone?->toString(), $user->email());
    }

    private static function disabledOwner(?User $user): User
    {
        if (null === $user || UserType::PORTAL !== $user->type() || UserStatus::DISABLED !== $user->status()) {
            throw new \LogicException('Only a disabled owner account is erased.');
        }

        return $user;
    }
}
