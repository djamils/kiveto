<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\Service;

use App\System\PharmaceuticalRegistry\Domain\Entity\DiffEntry;
use App\System\PharmaceuticalRegistry\Domain\Entity\SnapshotEntry;
use App\System\PharmaceuticalRegistry\Domain\Repository\MarketingAuthorizationHashProjection;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\DiffKind;

final class RegistryDiffCalculator
{
    /**
     * @param iterable<SnapshotEntry>                        $snapshotEntries streamed lazily
     * @param iterable<MarketingAuthorizationHashProjection> $projections
     *
     * @return DiffEntry[]
     */
    public function calculate(iterable $snapshotEntries, iterable $projections): array
    {
        $projectionMap = [];

        foreach ($projections as $projection) {
            $projectionMap[$projection->authorityIdentifier] = $projection;
        }

        $seenIdentifiers = [];
        $diffEntries     = [];

        foreach ($snapshotEntries as $entry) {
            $identifier                   = $entry->authorityIdentifier();
            $seenIdentifiers[$identifier] = true;

            if (!isset($projectionMap[$identifier])) {
                // rawDto is intentionally omitted — it lives in snapshot_entries.raw_dto
                // and will be loaded from DB at apply time via SnapshotMapper::toDomain().
                $diffEntries[] = new DiffEntry(
                    authorityIdentifier: $identifier,
                    diffKind: DiffKind::CREATE,
                    targetUuid: null,
                );
            } elseif (!$projectionMap[$identifier]->contentHash->equals($entry->contentHash())) {
                $diffEntries[] = new DiffEntry(
                    authorityIdentifier: $identifier,
                    diffKind: DiffKind::UPDATE,
                    targetUuid: $projectionMap[$identifier]->id,
                );
            }
        }

        foreach ($projectionMap as $identifier => $projection) {
            if (!isset($seenIdentifiers[$identifier])) {
                $diffEntries[] = new DiffEntry(
                    authorityIdentifier: $identifier,
                    diffKind: DiffKind::WITHDRAW,
                    targetUuid: $projection->id,
                );
            }
        }

        return $diffEntries;
    }
}
