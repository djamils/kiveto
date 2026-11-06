<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\ListBackofficeProfiles;

use App\System\IdentityAccess\Application\Port\BackofficeProfileStoreInterface;
use App\System\IdentityAccess\Application\Query\GetBackofficeProfile\BackofficeProfileView;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListBackofficeProfilesHandler
{
    public function __construct(
        private BackofficeProfileStoreInterface $profiles,
    ) {
    }

    /**
     * @return list<BackofficeProfileView>
     */
    public function __invoke(ListBackofficeProfiles $query): array
    {
        return $this->profiles->all();
    }
}
