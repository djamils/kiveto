<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Port;

use App\System\IdentityAccess\Application\Query\GetAccountSummaries\AccountSummary;
use App\System\IdentityAccess\Application\Query\ListUsers\UserCollection;
use App\System\IdentityAccess\Domain\ValueObject\UserType;

interface UserReadRepositoryInterface
{
    public function listAll(
        ?string $search = null,
        ?string $type = null,
        ?string $status = null,
    ): UserCollection;

    /**
     * The status of each known user among these ids.
     *
     * @param list<string> $userIds
     *
     * @return array<string, string> userId → status
     */
    public function statuses(array $userIds): array;

    /**
     * @param list<string> $userIds
     *
     * @return array<string, AccountSummary> userId → its account; unknown ids absent
     */
    public function summaries(array $userIds): array;

    /**
     * Accounts of one type whose e-mail or phone contains `text`, newest first.
     *
     * @return list<AccountSummary>
     */
    public function accounts(UserType $type, ?string $text, int $offset, int $limit): array;

    public function countAccounts(UserType $type, ?string $text): int;
}
