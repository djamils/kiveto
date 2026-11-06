<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\SearchAccounts;

use App\System\IdentityAccess\Application\Query\GetAccountSummaries\AccountSummary;

final readonly class AccountPage
{
    /**
     * @param list<AccountSummary> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }

    public function pageCount(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }
}
