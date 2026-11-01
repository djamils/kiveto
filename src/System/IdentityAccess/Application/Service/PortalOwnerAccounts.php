<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Service;

use App\System\IdentityAccess\Application\Exception\PortalAccountUnavailableException;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use Symfony\Component\Uid\Uuid;

/** The signed-in owner behind an action code: an ACTIVE owner account with a phone. */
final readonly class PortalOwnerAccounts
{
    public function __construct(private UserRepositoryInterface $users)
    {
    }

    /**
     * @param bool $lock read the account locked until the end of the transaction
     *
     * @throws PortalAccountUnavailableException
     */
    public function active(string $userId, bool $lock = false): User
    {
        $id   = Uuid::isValid($userId) ? UserId::fromString($userId) : null;
        $user = null === $id ? null : ($lock ? $this->users->findByIdForUpdate($id) : $this->users->findById($id));

        if (null === $user || UserType::PORTAL !== $user->type() || UserStatus::ACTIVE !== $user->status() || null === $user->phone()) {
            throw new PortalAccountUnavailableException();
        }

        return $user;
    }
}
