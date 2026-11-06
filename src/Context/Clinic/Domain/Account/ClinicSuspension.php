<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Account;

/**
 * Why and by whom a clinic was suspended, kept while it stays suspended
 * (« Clinique suspendue le 12 oct. 2026 par … · motif : fin de démo »).
 */
final class ClinicSuspension
{
    private const int MAX_COMMENT_LENGTH = 500;

    private SuspensionReason $reason;
    private string $comment;
    private \DateTimeImmutable $suspendedAt;
    private ?string $actorId;
    private string $actorName;

    private function __construct()
    {
    }

    public static function of(SuspensionReason $reason, string $comment, \DateTimeImmutable $suspendedAt, ?string $actorId, string $actorName): self
    {
        $actorName = trim($actorName);

        if ('' === $actorName) {
            throw new \InvalidArgumentException('A suspension names who decided it.');
        }

        $suspension              = new self();
        $suspension->reason      = $reason;
        $suspension->comment     = mb_substr(trim($comment), 0, self::MAX_COMMENT_LENGTH);
        $suspension->suspendedAt = $suspendedAt;
        $suspension->actorId     = $actorId;
        $suspension->actorName   = mb_substr($actorName, 0, 160);

        return $suspension;
    }

    public function reason(): SuspensionReason
    {
        return $this->reason;
    }

    public function comment(): string
    {
        return $this->comment;
    }

    public function suspendedAt(): \DateTimeImmutable
    {
        return $this->suspendedAt;
    }

    public function actorId(): ?string
    {
        return $this->actorId;
    }

    public function actorName(): string
    {
        return $this->actorName;
    }
}
