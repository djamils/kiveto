<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Port;

use App\System\IdentityAccess\Application\Query\GetBackofficeProfile\BackofficeProfileView;

/**
 * The console settings of a back-office account: the name it signs with,
 * its display time zone, the e-mails it wants. An account without a row
 * reads the defaults.
 */
interface BackofficeProfileStoreInterface
{
    public function save(
        string $userId,
        string $displayName,
        string $timeZone,
        bool $notifyOnRegistryFailure,
        bool $notifyOnInvitationExpiry,
        \DateTimeImmutable $at,
    ): void;

    /**
     * Every ACTIVE back-office account with its settings (defaults
     * applied), by name.
     *
     * @return list<BackofficeProfileView>
     */
    public function all(): array;

    public function find(string $userId): ?BackofficeProfileView;
}
