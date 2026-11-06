<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Support;

/**
 * D1–D3 — a member of the Kiveto team signed in to a clinic as one of its
 * people, for a reason, for a while, read-only unless allowed to write.
 *
 * The console issues it with a one-time hand-off secret (two minutes); the
 * clinic host claims it once and opens the session. Pages seen and writes
 * are counted; it ends by « Terminer la session » or when its time is up.
 */
final class SupportSession
{
    public const int HANDOFF_SECONDS = 120;

    public const array DURATIONS = [15, 30, 60];

    private function __construct(
        private readonly string $id,
        private readonly string $clinicId,
        private readonly string $userId,
        private readonly string $adminId,
        private readonly string $adminName,
        private readonly string $reason,
        private readonly SupportMode $mode,
        private readonly \DateTimeImmutable $startedAt,
        private readonly \DateTimeImmutable $expiresAt,
        private readonly string $handoffHash,
        private ?\DateTimeImmutable $claimedAt,
        private ?\DateTimeImmutable $endedAt,
        private int $pages,
        private int $writes,
    ) {
    }

    /**
     * @throws \InvalidArgumentException no reason, or a duration the console does not offer
     */
    public static function start(string $id, string $clinicId, string $userId, string $adminId, string $adminName, string $reason, SupportMode $mode, int $minutes, string $handoffHash, \DateTimeImmutable $at): self
    {
        $reason = trim($reason);

        if ('' === $reason) {
            throw new \InvalidArgumentException('Le motif est obligatoire : il est journalisé.');
        }

        if (!\in_array($minutes, self::DURATIONS, true)) {
            throw new \InvalidArgumentException('Durée de session non proposée.');
        }

        return new self($id, $clinicId, $userId, $adminId, mb_substr(trim($adminName), 0, 160), mb_substr($reason, 0, 500), $mode, $at, $at->modify(\sprintf('+%d minutes', $minutes)), $handoffHash, null, null, 0, 0);
    }

    public static function reconstitute(
        string $id,
        string $clinicId,
        string $userId,
        string $adminId,
        string $adminName,
        string $reason,
        SupportMode $mode,
        \DateTimeImmutable $startedAt,
        \DateTimeImmutable $expiresAt,
        string $handoffHash,
        ?\DateTimeImmutable $claimedAt,
        ?\DateTimeImmutable $endedAt,
        int $pages,
        int $writes,
    ): self {
        return new self($id, $clinicId, $userId, $adminId, $adminName, $reason, $mode, $startedAt, $expiresAt, $handoffHash, $claimedAt, $endedAt, $pages, $writes);
    }

    /**
     * The clinic host takes the hand-off: once, within two minutes.
     */
    public function claim(\DateTimeImmutable $at): bool
    {
        if (null !== $this->claimedAt || null !== $this->endedAt || $at->getTimestamp() - $this->startedAt->getTimestamp() > self::HANDOFF_SECONDS) {
            return false;
        }

        $this->claimedAt = $at;

        return true;
    }

    public function isOpenAt(\DateTimeImmutable $at): bool
    {
        return null !== $this->claimedAt && null === $this->endedAt && $at < $this->expiresAt;
    }

    public function recordPage(bool $write): void
    {
        ++$this->pages;
        $this->writes += $write ? 1 : 0;
    }

    /**
     * Ended at the time asked, or at its expiry when that came first. Once.
     */
    public function end(\DateTimeImmutable $at): bool
    {
        if (null !== $this->endedAt) {
            return false;
        }

        $this->endedAt = min($at, $this->expiresAt);

        return true;
    }

    public function minutes(): int
    {
        $end = $this->endedAt ?? $this->expiresAt;

        return max(1, (int) round(($end->getTimestamp() - ($this->claimedAt ?? $this->startedAt)->getTimestamp()) / 60));
    }

    public function id(): string
    {
        return $this->id;
    }

    public function clinicId(): string
    {
        return $this->clinicId;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function adminId(): string
    {
        return $this->adminId;
    }

    public function adminName(): string
    {
        return $this->adminName;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function mode(): SupportMode
    {
        return $this->mode;
    }

    public function startedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function handoffHash(): string
    {
        return $this->handoffHash;
    }

    public function claimedAt(): ?\DateTimeImmutable
    {
        return $this->claimedAt;
    }

    public function endedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function pages(): int
    {
        return $this->pages;
    }

    public function writes(): int
    {
        return $this->writes;
    }
}
