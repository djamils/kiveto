<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\GetAccountSummaries;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The accounts of these ids (unknown ids are absent), keyed by id.
 */
final readonly class GetAccountSummaries implements QueryInterface
{
    /**
     * @param list<string> $userIds
     */
    public function __construct(
        public array $userIds,
    ) {
    }
}
