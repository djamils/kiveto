<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\IdentityAccess;

use App\Context\ClientPortal\Application\Port\OwnerAccount;
use App\Context\ClientPortal\Application\Port\OwnerAccountsInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\IdentityAccess\Application\Query\GetPortalAccount\GetPortalAccount;
use App\System\IdentityAccess\Application\Query\GetPortalAccount\PortalAccount;

/** IdentityAccess's GetPortalAccount. */
final readonly class QueryBusOwnerAccounts implements OwnerAccountsInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function accountOf(string $userId): ?OwnerAccount
    {
        $account = $this->queryBus->ask(new GetPortalAccount($userId));

        if (!$account instanceof PortalAccount) {
            return null;
        }

        return new OwnerAccount($account->userId, $account->phone, $account->email, $account->emailVerified);
    }
}
