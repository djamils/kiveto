<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\ListConsoleTeam;

use App\System\IdentityAccess\Application\Port\BackofficeProfileStoreInterface;
use App\System\IdentityAccess\Application\Query\GetBackofficeProfile\BackofficeProfileView;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListConsoleTeamHandler
{
    public function __construct(
        private BackofficeProfileStoreInterface $profiles,
    ) {
    }

    /**
     * @return list<BackofficeProfileView>
     */
    public function __invoke(ListConsoleTeam $query): array
    {
        return $this->profiles->team();
    }
}
