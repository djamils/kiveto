<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\GetPortalAccount;

use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/** Only an active owner account with a phone is answered. */
#[AsMessageHandler]
final readonly class GetPortalAccountHandler
{
    public function __construct(private UserRepositoryInterface $users)
    {
    }

    public function __invoke(GetPortalAccount $query): ?PortalAccount
    {
        $user  = Uuid::isValid($query->userId) ? $this->users->findById(UserId::fromString($query->userId)) : null;
        $phone = $user?->phone();

        if (null === $user || null === $phone || UserType::PORTAL !== $user->type() || UserStatus::ACTIVE !== $user->status()) {
            return null;
        }

        return new PortalAccount($user->id()->toString(), $user->email(), $phone->toString());
    }
}
