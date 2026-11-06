<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\Console;

/**
 * An import of the registry (G1–G4): when, how it ended, what it changed.
 */
final readonly class RegistryImportView
{
    public function __construct(
        public string $id,
        public string $status,
        public \DateTimeImmutable $downloadedAt,
        public ?\DateTimeImmutable $appliedAt,
        public ?string $errorMessage,
        public int $created,
        public int $updated,
        public int $withdrawn,
    ) {
    }

    public function changes(): int
    {
        return $this->created + $this->updated + $this->withdrawn;
    }

    public function isRunning(): bool
    {
        return \in_array($this->status, ['PENDING', 'RUNNING', 'DIFFED', 'APPLYING'], true);
    }

    public function isFailed(): bool
    {
        return 'FAILED' === $this->status;
    }

    public function isApplied(): bool
    {
        return 'APPLIED' === $this->status;
    }

    /** Seconds from the download to the end, when it ended. */
    public function seconds(): ?int
    {
        return null !== $this->appliedAt ? max(0, $this->appliedAt->getTimestamp() - $this->downloadedAt->getTimestamp()) : null;
    }
}
