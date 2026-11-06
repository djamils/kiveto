<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\SecondFactor;

/**
 * A one-time code kept on paper for a lost phone: only its digest is stored.
 */
final class BackupCode
{
    private string $hash;
    private ?\DateTimeImmutable $usedAt;

    private function __construct()
    {
    }

    public static function unused(string $hash): self
    {
        return self::reconstitute($hash, null);
    }

    public static function reconstitute(string $hash, ?\DateTimeImmutable $usedAt): self
    {
        if ('' === $hash) {
            throw new \InvalidArgumentException('A backup code needs its digest.');
        }

        $code         = new self();
        $code->hash   = $hash;
        $code->usedAt = $usedAt;

        return $code;
    }

    public function hash(): string
    {
        return $this->hash;
    }

    public function usedAt(): ?\DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function isUsed(): bool
    {
        return null !== $this->usedAt;
    }

    public function use(\DateTimeImmutable $at): self
    {
        return self::reconstitute($this->hash, $at);
    }
}
