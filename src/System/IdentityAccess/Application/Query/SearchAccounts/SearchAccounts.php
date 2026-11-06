<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\SearchAccounts;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Accounts of one type (CLINIC, PORTAL, BACKOFFICE) whose e-mail or phone
 * contains `text`, newest first: the console's search and its owners tab.
 */
final readonly class SearchAccounts implements QueryInterface
{
    public function __construct(
        public string $type,
        public ?string $text = null,
        public int $page = 1,
        public int $perPage = 10,
    ) {
    }
}
