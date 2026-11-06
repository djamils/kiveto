<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\GetBackofficeProfile;

/**
 * A back-office account with its console settings, its role ('admin',
 * 'support') and its state ('ACTIVE', 'PENDING': invited, 'DISABLED').
 */
final readonly class BackofficeProfileView
{
    public const string DEFAULT_TIME_ZONE = 'Europe/Paris';

    public function __construct(
        public string $userId,
        public string $email,
        public string $displayName,
        public string $timeZone,
        public bool $notifyOnRegistryFailure,
        public bool $notifyOnInvitationExpiry,
        public \DateTimeImmutable $memberSince,
        public string $role = 'admin',
        public string $status = 'ACTIVE',
    ) {
    }

    public function isAdmin(): bool
    {
        return 'admin' === $this->role;
    }

    /**
     * The name of an account that never chose one: the part of its address
     * before the at sign, words capitalised (« djamil.soidiki » → « Djamil
     * Soidiki »).
     */
    public static function nameFromEmail(string $email): string
    {
        $local = (string) strstr($email, '@', true) ?: $email;
        $words = preg_split('/[._\-+]+/', $local) ?: [];

        return implode(' ', array_map(static fn (string $word): string => mb_convert_case($word, \MB_CASE_TITLE), array_filter($words, static fn (string $w): bool => '' !== $w))) ?: $email;
    }
}
