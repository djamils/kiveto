<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Adapter\IdentityAccess;

use App\Context\Clinic\Application\Port\UserAccountStatusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\IdentityAccess\Application\Query\GetUserStatuses\GetUserStatuses;

final readonly class QueryBusUserAccountStatus implements UserAccountStatusInterface
{
    private const string ACTIVE = 'ACTIVE';

    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function activeUserIds(array $userIds): array
    {
        $statuses = $this->queryBus->ask(new GetUserStatuses($userIds));
        \assert(\is_array($statuses));

        return array_values(array_filter($userIds, static fn (string $id): bool => self::ACTIVE === ($statuses[$id] ?? null)));
    }
}
