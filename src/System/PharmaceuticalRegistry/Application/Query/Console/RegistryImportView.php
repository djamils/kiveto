<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\Console;

/**
 * An import of the registry (G1–G4): when, how it ended, what it changed,
 * the source files it read (none for the imports before the journal).
 */
final readonly class RegistryImportView
{
    /**
     * @param list<RegistryImportFile> $files
     */
    public function __construct(
        public string $id,
        public string $status,
        public \DateTimeImmutable $downloadedAt,
        public ?\DateTimeImmutable $appliedAt,
        public ?string $errorMessage,
        public int $created,
        public int $updated,
        public int $withdrawn,
        public array $files = [],
    ) {
    }

    /**
     * @param list<RegistryImportFile> $files
     */
    public function withFiles(array $files): self
    {
        return new self($this->id, $this->status, $this->downloadedAt, $this->appliedAt, $this->errorMessage, $this->created, $this->updated, $this->withdrawn, $files);
    }

    public function registryFile(): ?RegistryImportFile
    {
        foreach ($this->files as $file) {
            if (RegistryImportFile::REGISTRY === $file->kind) {
                return $file;
            }
        }

        return null;
    }

    /** Who launched it: an admin of the console, the command line; null before the journal. */
    public function importedBy(): ?string
    {
        return $this->registryFile()?->importedBy;
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
