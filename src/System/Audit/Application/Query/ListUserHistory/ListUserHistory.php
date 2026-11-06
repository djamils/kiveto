<?php

declare(strict_types=1);

namespace App\System\Audit\Application\Query\ListUserHistory;

use App\Shared\Application\Bus\QueryInterface;

/**
 * « Historique du compte » of a user's sheet: what the account did and what
 * was done to it.
 */
final readonly class ListUserHistory implements QueryInterface
{
    public function __construct(
        public string $userId,
        public int $limit = 5,
    ) {
    }
}
