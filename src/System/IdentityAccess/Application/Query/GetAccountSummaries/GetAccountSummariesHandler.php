<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\GetAccountSummaries;

use App\System\IdentityAccess\Application\Port\UserReadRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetAccountSummariesHandler
{
    public function __construct(
        private UserReadRepositoryInterface $users,
    ) {
    }

    /**
     * @return array<string, AccountSummary>
     */
    public function __invoke(GetAccountSummaries $query): array
    {
        return $this->users->summaries(array_values(array_unique($query->userIds)));
    }
}
