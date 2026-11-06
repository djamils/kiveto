<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query;

use App\System\IdentityAccess\Domain\Support\SupportSession;

/**
 * A support session as the console and the VetApp banner show it.
 */
final readonly class SupportSessionView
{
    public function __construct(
        public string $id,
        public string $clinicId,
        public string $userId,
        public string $adminId,
        public string $adminName,
        public string $reason,
        public string $mode,
        public string $modeLabel,
        public \DateTimeImmutable $startedAt,
        public \DateTimeImmutable $expiresAt,
        public ?\DateTimeImmutable $endedAt,
        public int $minutes,
        public int $pages,
        public int $writes,
    ) {
    }

    public static function of(SupportSession $session): self
    {
        return new self(
            id: $session->id(),
            clinicId: $session->clinicId(),
            userId: $session->userId(),
            adminId: $session->adminId(),
            adminName: $session->adminName(),
            reason: $session->reason(),
            mode: $session->mode()->value,
            modeLabel: $session->mode()->label(),
            startedAt: $session->startedAt(),
            expiresAt: $session->expiresAt(),
            endedAt: $session->endedAt(),
            minutes: $session->minutes(),
            pages: $session->pages(),
            writes: $session->writes(),
        );
    }

    public function isReadOnly(): bool
    {
        return 'read' === $this->mode;
    }
}
