<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\GetUserStatuses;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The account status (PENDING, ACTIVE, DISABLED) of each of these users;
 * an unknown id is absent from the answer.
 */
final readonly class GetUserStatuses implements QueryInterface
{
    /**
     * @param list<string> $userIds
     */
    public function __construct(public array $userIds)
    {
    }
}
