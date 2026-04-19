<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Adapter\IdentityAccess;

use App\Context\Clinic\Application\Port\UserSearchForOnboardingInterface;
use App\Context\Clinic\Application\Port\UserSearchResultItem;
use App\System\IdentityAccess\Application\Port\UserReadRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;

final readonly class IdentityAccessUserSearchAdapter implements UserSearchForOnboardingInterface
{
    private const int MIN_FRAGMENT_LENGTH = 2;

    public function __construct(
        private UserReadRepositoryInterface $userReadRepository,
    ) {
    }

    public function searchByEmail(string $emailFragment, int $limit): array
    {
        if (mb_strlen($emailFragment) < self::MIN_FRAGMENT_LENGTH) {
            throw new \InvalidArgumentException(\sprintf(
                'Email fragment must be at least %d characters long.',
                self::MIN_FRAGMENT_LENGTH,
            ));
        }

        $collection = $this->userReadRepository->listAll(
            search: $emailFragment,
            type: UserType::CLINIC->value,
            status: UserStatus::ACTIVE->value,
        );

        $results = [];
        $count   = 0;

        foreach ($collection->users as $user) {
            if ($count >= $limit) {
                break;
            }

            $results[] = new UserSearchResultItem(
                userId: $user->id,
                email: $user->email,
            );

            ++$count;
        }

        return $results;
    }
}
