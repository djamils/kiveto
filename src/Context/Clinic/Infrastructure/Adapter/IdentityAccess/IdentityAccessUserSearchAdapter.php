<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Adapter\IdentityAccess;

use App\Context\Clinic\Application\Port\UserSearchForOnboardingInterface;
use App\Context\Clinic\Application\Port\UserSearchResultItem;
use App\System\IdentityAccess\Application\Port\UserReadRepositoryInterface;

/**
 * Clinic accounts that can join a team: ACTIVE ones, and PENDING ones (an
 * invitation not accepted yet is sent again rather than colliding on the
 * unique address). Disabled accounts are left out.
 */
final readonly class IdentityAccessUserSearchAdapter implements UserSearchForOnboardingInterface
{
    private const int MIN_FRAGMENT_LENGTH = 2;
    private const string CLINIC           = 'CLINIC';
    private const array JOINABLE          = ['ACTIVE', 'PENDING'];

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

        $results = [];

        foreach ($this->userReadRepository->listAll(search: $emailFragment, type: self::CLINIC)->users as $user) {
            if (\count($results) >= $limit) {
                break;
            }

            if (\in_array($user->status, self::JOINABLE, true)) {
                $results[] = new UserSearchResultItem(userId: $user->id, email: $user->email);
            }
        }

        return $results;
    }
}
