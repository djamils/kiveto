<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Connection;

/**
 * Where the last synchronisation stopped: the flows of the platform are read
 * in (updatedAt, flowId) order, strictly after this pair — flows sharing one
 * timestamp are ordered by id, so a run cut short skips none.
 */
final class SyncCursor
{
    private function __construct(
        private readonly \DateTimeImmutable $updatedAfter,
        private readonly string $lastFlowId,
    ) {
    }

    public static function at(\DateTimeImmutable $updatedAfter, string $lastFlowId = ''): self
    {
        return new self($updatedAfter, $lastFlowId);
    }

    public function updatedAfter(): \DateTimeImmutable
    {
        return $this->updatedAfter;
    }

    public function lastFlowId(): string
    {
        return $this->lastFlowId;
    }

    /**
     * Whether a flow updated at $updatedAt with id $flowId comes after the cursor.
     */
    public function precedes(\DateTimeImmutable $updatedAt, string $flowId): bool
    {
        $comparison = $this->updatedAfter <=> $updatedAt;

        return $comparison < 0 || (0 === $comparison && strcmp($this->lastFlowId, $flowId) < 0);
    }

    public function isAfter(self $other): bool
    {
        return $other->precedes($this->updatedAfter, $this->lastFlowId);
    }

    public function equals(self $other): bool
    {
        return $this->updatedAfter === $other->updatedAfter && $this->lastFlowId === $other->lastFlowId;
    }
}
