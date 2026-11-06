<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\SecondFactor;

use App\System\IdentityAccess\Domain\ValueObject\UserId;

/**
 * A browser the admin chose to trust after a second factor: for 30 days its
 * cookie (only its digest is stored) skips the code.
 */
final class TrustedDevice
{
    public const int DAYS = 30;

    private string $id;
    private UserId $userId;
    private string $tokenHash;
    private string $label;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $expiresAt;
    private ?\DateTimeImmutable $revokedAt;

    private function __construct()
    {
    }

    public static function trust(string $id, UserId $userId, string $tokenHash, string $label, \DateTimeImmutable $at): self
    {
        return self::reconstitute($id, $userId, $tokenHash, mb_substr(trim($label), 0, 120), $at, $at->modify(\sprintf('+%d days', self::DAYS)), null);
    }

    public static function reconstitute(
        string $id,
        UserId $userId,
        string $tokenHash,
        string $label,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $expiresAt,
        ?\DateTimeImmutable $revokedAt,
    ): self {
        $device            = new self();
        $device->id        = $id;
        $device->userId    = $userId;
        $device->tokenHash = $tokenHash;
        $device->label     = $label;
        $device->createdAt = $createdAt;
        $device->expiresAt = $expiresAt;
        $device->revokedAt = $revokedAt;

        return $device;
    }

    public function isUsableAt(\DateTimeImmutable $now): bool
    {
        return null === $this->revokedAt && $now < $this->expiresAt;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function revokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }
}
