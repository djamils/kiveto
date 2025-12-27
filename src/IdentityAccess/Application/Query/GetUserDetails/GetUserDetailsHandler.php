<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\GetUserDetails;

use App\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\IdentityAccess\Domain\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class GetUserDetailsHandler
{
    public function __construct(private UserRepositoryInterface $userRepository)
    {
    }

    public function __invoke(GetUserDetails $query): ?UserDetails
    {
        $userId = UserId::fromString($query->userId);
        $user   = $this->userRepository->findById($userId);

        if (null === $user) {
            return null;
        }

        return new UserDetails(
            $user->id()->toString(),
            $user->email(),
            $user->createdAt()->format(\DateTimeInterface::ATOM),
            $user->status()->value,
            $user->emailVerifiedAt()?->format(\DateTimeInterface::ATOM),
        );
    }
}
