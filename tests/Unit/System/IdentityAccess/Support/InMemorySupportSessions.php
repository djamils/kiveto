<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Support;

use App\System\IdentityAccess\Domain\Support\SupportSession;
use App\System\IdentityAccess\Domain\Support\SupportSessionRepositoryInterface;

final class InMemorySupportSessions implements SupportSessionRepositoryInterface
{
    /** @var array<string, SupportSession> */
    public array $sessions = [];

    /** @var list<string> ids saved, in order */
    public array $saved = [];

    /** @var list<string> hand-off digests read with a lock, in order */
    public array $locked = [];

    public function save(SupportSession $session): void
    {
        $this->sessions[$session->id()] = $session;
        $this->saved[]                  = $session->id();
    }

    public function find(string $id): ?SupportSession
    {
        return $this->sessions[$id] ?? null;
    }

    public function findByHandoffHashForUpdate(string $handoffHash): ?SupportSession
    {
        $this->locked[] = $handoffHash;

        foreach ($this->sessions as $session) {
            if ($session->handoffHash() === $handoffHash) {
                return $session;
            }
        }

        return null;
    }

    public function ofClinic(string $clinicId, int $limit): array
    {
        $sessions = array_values(array_filter(
            $this->sessions,
            static fn (SupportSession $session): bool => $session->clinicId() === $clinicId && null !== $session->claimedAt(),
        ));
        usort($sessions, static fn (SupportSession $a, SupportSession $b): int => $b->startedAt() <=> $a->startedAt());

        return \array_slice($sessions, 0, $limit);
    }

    /**
     * A session already in the store, without the bookkeeping of a save.
     */
    public function add(SupportSession $session): SupportSession
    {
        $this->sessions[$session->id()] = $session;

        return $session;
    }
}
