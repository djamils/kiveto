<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\GetUserStatuses;

use App\System\IdentityAccess\Application\Port\UserReadRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetUserStatusesHandler
{
    public function __construct(private UserReadRepositoryInterface $users)
    {
    }

    /**
     * @return array<string, string> userId → status
     */
    public function __invoke(GetUserStatuses $query): array
    {
        if ([] === $query->userIds) {
            return [];
        }

        return $this->users->statuses(array_values(array_unique($query->userIds)));
    }
}
