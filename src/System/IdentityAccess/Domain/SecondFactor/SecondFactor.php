<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\SecondFactor;

use App\System\IdentityAccess\Domain\ValueObject\UserId;

/**
 * The second factor of a back-office account: its authenticator app and its
 * ten backup codes. A TOTP code is accepted once (the last step used is
 * kept), a backup code once.
 */
final class SecondFactor
{
    public const int BACKUP_CODE_COUNT = 10;

    private UserId $userId;
    private TotpSecret $secret;
    private \DateTimeImmutable $enabledAt;
    private ?int $lastUsedStep;
    /** @var list<BackupCode> */
    private array $backupCodes;

    private function __construct()
    {
    }

    /**
     * The admin scanned the key and typed a code it gave: the key is proven,
     * the factor is enabled with fresh backup codes.
     *
     * @param list<string> $backupCodeHashes
     */
    public static function enable(
        UserId $userId,
        TotpSecret $secret,
        string $confirmationCode,
        array $backupCodeHashes,
        \DateTimeImmutable $at,
    ): self {
        $step = Totp::matchingStep($secret, $confirmationCode, $at, null);

        if (null === $step) {
            throw new InvalidSecondFactorCodeException();
        }

        return self::reconstitute($userId, $secret, $at, $step, self::freshCodes($backupCodeHashes));
    }

    /**
     * @param list<BackupCode> $backupCodes
     */
    public static function reconstitute(
        UserId $userId,
        TotpSecret $secret,
        \DateTimeImmutable $enabledAt,
        ?int $lastUsedStep,
        array $backupCodes,
    ): self {
        $factor               = new self();
        $factor->userId       = $userId;
        $factor->secret       = $secret;
        $factor->enabledAt    = $enabledAt;
        $factor->lastUsedStep = $lastUsedStep;
        $factor->backupCodes  = $backupCodes;

        return $factor;
    }

    /**
     * A code of the app, not used before.
     */
    public function verifyCode(string $code, \DateTimeImmutable $now): bool
    {
        $step = Totp::matchingStep($this->secret, $code, $now, $this->lastUsedStep);

        if (null === $step) {
            return false;
        }

        $this->lastUsedStep = $step;

        return true;
    }

    /**
     * An unused backup code (its digest): spent.
     */
    public function useBackupCode(string $hash, \DateTimeImmutable $at): bool
    {
        foreach ($this->backupCodes as $index => $code) {
            if (!$code->isUsed() && hash_equals($code->hash(), $hash)) {
                $this->backupCodes[$index] = $code->use($at);

                return true;
            }
        }

        return false;
    }

    /**
     * « Régénérer »: the previous codes stop working.
     *
     * @param list<string> $backupCodeHashes
     */
    public function replaceBackupCodes(array $backupCodeHashes): void
    {
        $this->backupCodes = self::freshCodes($backupCodeHashes);
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function secret(): TotpSecret
    {
        return $this->secret;
    }

    public function enabledAt(): \DateTimeImmutable
    {
        return $this->enabledAt;
    }

    public function lastUsedStep(): ?int
    {
        return $this->lastUsedStep;
    }

    /**
     * @return list<BackupCode>
     */
    public function backupCodes(): array
    {
        return $this->backupCodes;
    }

    public function remainingBackupCodes(): int
    {
        return \count(array_filter($this->backupCodes, static fn (BackupCode $code): bool => !$code->isUsed()));
    }

    /**
     * @param list<string> $hashes
     *
     * @return list<BackupCode>
     */
    private static function freshCodes(array $hashes): array
    {
        if (self::BACKUP_CODE_COUNT !== \count(array_unique($hashes))) {
            throw new \InvalidArgumentException(\sprintf('A second factor comes with %d distinct backup codes.', self::BACKUP_CODE_COUNT));
        }

        return array_map(BackupCode::unused(...), $hashes);
    }
}
