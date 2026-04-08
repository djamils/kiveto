<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\ListUsers;

use App\System\IdentityAccess\Application\Port\UserReadRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListUsersHandler
{
    public function __construct(
        private UserReadRepositoryInterface $userReadRepository,
    ) {
    }

    public function __invoke(ListUsers $query): UserCollection
    {
        return $this->userReadRepository->listAll(
            search: $query->search,
            type: $query->type,
            status: $query->status,
        );
    }
}
