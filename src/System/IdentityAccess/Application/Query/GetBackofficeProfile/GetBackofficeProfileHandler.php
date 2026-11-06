<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\GetBackofficeProfile;

use App\System\IdentityAccess\Application\Port\BackofficeProfileStoreInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Null for an id that is no back-office account.
 */
#[AsMessageHandler]
final readonly class GetBackofficeProfileHandler
{
    public function __construct(
        private BackofficeProfileStoreInterface $profiles,
    ) {
    }

    public function __invoke(GetBackofficeProfile $query): ?BackofficeProfileView
    {
        return $this->profiles->find($query->userId);
    }
}
