<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\System\PharmaceuticalRegistry\Domain\Entity\DiffEntry;
use App\System\PharmaceuticalRegistry\Domain\Entity\SnapshotEntry;
use App\System\PharmaceuticalRegistry\Domain\Event\SnapshotApplied;
use App\System\PharmaceuticalRegistry\Domain\Event\SnapshotDiffed;
use App\System\PharmaceuticalRegistry\Domain\Event\SnapshotFailed;
use App\System\PharmaceuticalRegistry\Domain\Event\SnapshotStarted;
use App\System\PharmaceuticalRegistry\Domain\Exception\InvalidSnapshotStatusTransitionException;
use App\System\PharmaceuticalRegistry\Domain\Exception\SnapshotAlreadyAppliedException;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ContentHash;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportResult;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportSource;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportStatus;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\SnapshotId;

class Snapshot extends AggregateRoot
{
    private SnapshotId $id;
    private ImportSource $source;
    private ImportStatus $status;
    private \DateTimeImmutable $downloadedAt;
    private ?\DateTimeImmutable $appliedAt;
    private ?string $errorMessage;
    /** @var SnapshotEntry[] */
    private array $entries;
    /** @var DiffEntry[] */
    private array $diffEntries;

    private function __construct()
    {
    }

    public static function create(SnapshotId $id, ImportSource $source, \DateTimeImmutable $now): self
    {
        $snapshot               = new self();
        $snapshot->id           = $id;
        $snapshot->source       = $source;
        $snapshot->status       = ImportStatus::PENDING;
        $snapshot->downloadedAt = $now;
        $snapshot->appliedAt    = null;
        $snapshot->errorMessage = null;
        $snapshot->entries      = [];
        $snapshot->diffEntries  = [];

        $snapshot->recordDomainEvent(new SnapshotStarted(
            snapshotId: $id->toString(),
            source: $source->value,
        ));

        return $snapshot;
    }

    /**
     * @param SnapshotEntry[] $entries
     * @param DiffEntry[]     $diffEntries
     */
    public static function reconstitute(
        SnapshotId $id,
        ImportSource $source,
        ImportStatus $status,
        \DateTimeImmutable $downloadedAt,
        ?\DateTimeImmutable $appliedAt,
        ?string $errorMessage,
        array $entries,
        array $diffEntries,
    ): self {
        $snapshot               = new self();
        $snapshot->id           = $id;
        $snapshot->source       = $source;
        $snapshot->status       = $status;
        $snapshot->downloadedAt = $downloadedAt;
        $snapshot->appliedAt    = $appliedAt;
        $snapshot->errorMessage = $errorMessage;
        $snapshot->entries      = $entries;
        $snapshot->diffEntries  = $diffEntries;

        return $snapshot;
    }

    public function markAsRunning(\DateTimeImmutable $now): void
    {
        if (ImportStatus::PENDING !== $this->status) {
            throw new InvalidSnapshotStatusTransitionException($this->status, ImportStatus::RUNNING);
        }

        $this->status = ImportStatus::RUNNING;
    }

    /** @param array<mixed> $rawDto */
    public function addEntry(string $authorityIdentifier, ContentHash $contentHash, array $rawDto): void
    {
        $invalidForEntry = [ImportStatus::DIFFED, ImportStatus::APPLYING, ImportStatus::APPLIED, ImportStatus::FAILED];

        if (\in_array($this->status, $invalidForEntry, true)) {
            throw new \DomainException(\sprintf(
                'Cannot add entries to a snapshot with status "%s".',
                $this->status->value,
            ));
        }

        $this->entries[] = new SnapshotEntry($authorityIdentifier, $contentHash, $rawDto);
    }

    /**
     * @param DiffEntry[] $entries
     */
    public function markAsDiffed(array $entries): void
    {
        if (ImportStatus::RUNNING !== $this->status) {
            throw new InvalidSnapshotStatusTransitionException($this->status, ImportStatus::DIFFED);
        }

        $this->status      = ImportStatus::DIFFED;
        $this->diffEntries = $entries;

        $createCount   = 0;
        $updateCount   = 0;
        $withdrawCount = 0;

        foreach ($entries as $entry) {
            match ($entry->diffKind()) {
                ValueObject\DiffKind::CREATE   => ++$createCount,
                ValueObject\DiffKind::UPDATE   => ++$updateCount,
                ValueObject\DiffKind::WITHDRAW => ++$withdrawCount,
            };
        }

        $this->recordDomainEvent(new SnapshotDiffed(
            snapshotId: $this->id->toString(),
            createCount: $createCount,
            updateCount: $updateCount,
            withdrawCount: $withdrawCount,
        ));
    }

    public function markAsApplied(ImportResult $result, \DateTimeImmutable $now): void
    {
        if (ImportStatus::APPLIED === $this->status) {
            throw new SnapshotAlreadyAppliedException($this->id->toString());
        }

        if (ImportStatus::DIFFED !== $this->status && ImportStatus::APPLYING !== $this->status) {
            throw new InvalidSnapshotStatusTransitionException($this->status, ImportStatus::APPLIED);
        }

        $this->status    = ImportStatus::APPLIED;
        $this->appliedAt = $now;

        $this->recordDomainEvent(new SnapshotApplied(
            snapshotId: $this->id->toString(),
            created: $result->created(),
            updated: $result->updated(),
            withdrawn: $result->withdrawn(),
            skipped: $result->skipped(),
        ));
    }

    public function markAsFailed(string $error, \DateTimeImmutable $now): void
    {
        $this->status       = ImportStatus::FAILED;
        $this->errorMessage = $error;

        $this->recordDomainEvent(new SnapshotFailed(
            snapshotId: $this->id->toString(),
            errorMessage: $error,
        ));
    }

    public function id(): SnapshotId
    {
        return $this->id;
    }

    public function source(): ImportSource
    {
        return $this->source;
    }

    public function status(): ImportStatus
    {
        return $this->status;
    }

    public function downloadedAt(): \DateTimeImmutable
    {
        return $this->downloadedAt;
    }

    public function appliedAt(): ?\DateTimeImmutable
    {
        return $this->appliedAt;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /** @return SnapshotEntry[] */
    public function entries(): array
    {
        return $this->entries;
    }

    /** @return DiffEntry[] */
    public function diffEntries(): array
    {
        return $this->diffEntries;
    }
}
