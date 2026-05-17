<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\Repository;

use App\System\PharmaceuticalRegistry\Domain\Entity\SnapshotEntry;
use App\System\PharmaceuticalRegistry\Domain\Snapshot;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportSource;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\SnapshotId;

interface SnapshotRepositoryInterface
{
    public function save(Snapshot $snapshot): void;

    /** @param bool $withDiffEntries Set to false in memory-constrained contexts (bootstrap) to skip loading rawDto. */
    public function findById(SnapshotId $id, bool $withDiffEntries = true): ?Snapshot;

    /**
     * @return Snapshot[]
     */
    public function findRecent(ImportSource $source, int $limit): array;

    /**
     * Streams entries without loading them into the Snapshot aggregate.
     * Used by CalculateSnapshotDiffHandler to avoid OOMing on 14k entries.
     *
     * @return iterable<SnapshotEntry>
     */
    public function streamEntriesForDiff(SnapshotId $id): iterable;
}
