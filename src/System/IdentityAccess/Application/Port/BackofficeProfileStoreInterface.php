<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Port;

use App\System\IdentityAccess\Application\Query\GetBackofficeProfile\BackofficeProfileView;
use App\System\IdentityAccess\Domain\ValueObject\ConsoleRole;

/**
 * The console settings of a back-office account: the name it signs with,
 * its display time zone, the e-mails it wants, its role in the console. An
 * account without a row reads the defaults (an administrator).
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

    /** The role of a back-office account; an account without a row gets one with the defaults. */
    public function saveRole(string $userId, ConsoleRole $role, \DateTimeImmutable $at): void;

    /**
     * Every back-office account, whatever its state (« Équipe »): the
     * active ones first, then by name.
     *
     * @return list<BackofficeProfileView>
     */
    public function team(): array;
}
