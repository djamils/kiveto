<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Mapper;

use App\System\PharmaceuticalRegistry\Domain\Entity\DiffEntry;
use App\System\PharmaceuticalRegistry\Domain\Entity\SnapshotEntry;
use App\System\PharmaceuticalRegistry\Domain\Snapshot;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ContentHash;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\DiffKind;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportSource;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportStatus;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\MarketingAuthorizationId;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\SnapshotId;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\SnapshotEntity;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\SnapshotEntryEntity;
use Symfony\Component\Uid\Uuid;

final readonly class SnapshotMapper
{
    /** Does NOT load entries — entries are streamed separately via streamEntriesForDiff(). */
    public function toDomain(SnapshotEntity $entity): Snapshot
    {
        $diffEntries = [];

        foreach ($entity->getEntries() as $entryEntity) {
            if (null === $entryEntity->getDiffKind()) {
                continue;
            }

            $diffKind = DiffKind::from($entryEntity->getDiffKind());
            $targetId = null !== $entryEntity->getTargetUuid()
                ? MarketingAuthorizationId::fromString($entryEntity->getTargetUuid()->toString())
                : null;

            $diffEntries[] = new DiffEntry(
                authorityIdentifier: $entryEntity->getAuthorityIdentifier(),
                diffKind: $diffKind,
                targetUuid: $targetId,
                changes: $entryEntity->getChanges(),
                rawDto: $entryEntity->getRawDto(),
            );
        }

        return Snapshot::reconstitute(
            id: SnapshotId::fromString($entity->getId()->toString()),
            source: ImportSource::from($entity->getSource()),
            status: ImportStatus::from($entity->getStatus()),
            downloadedAt: $entity->getDownloadedAt(),
            appliedAt: $entity->getAppliedAt(),
            errorMessage: $entity->getErrorMessage(),
            entries: [],
            diffEntries: $diffEntries,
        );
    }

    public function toEntity(Snapshot $snapshot): SnapshotEntity
    {
        $entity = new SnapshotEntity();
        $entity->setId(Uuid::fromString($snapshot->id()->toString()));
        $entity->setSource($snapshot->source()->value);
        $entity->setStatus($snapshot->status()->value);
        $entity->setDownloadedAt($snapshot->downloadedAt());
        $entity->setAppliedAt($snapshot->appliedAt());
        $entity->setErrorMessage($snapshot->errorMessage());

        return $entity;
    }

    public function snapshotEntryToDomain(SnapshotEntryEntity $entity): SnapshotEntry
    {
        return new SnapshotEntry(
            authorityIdentifier: $entity->getAuthorityIdentifier(),
            contentHash: ContentHash::fromString($entity->getContentHash()),
            rawDto: $entity->getRawDto(),
        );
    }

    public function snapshotEntryToEntity(
        SnapshotEntry $entry,
        SnapshotEntity $snapshot,
    ): SnapshotEntryEntity {
        $entity = new SnapshotEntryEntity();
        $entity->setId(Uuid::v7());
        $entity->setSnapshot($snapshot);
        $entity->setAuthorityIdentifier($entry->authorityIdentifier());
        $entity->setContentHash($entry->contentHash()->toString());
        $entity->setRawDto($entry->rawDto());
        $entity->setDiffKind(null);
        $entity->setTargetUuid(null);
        $entity->setChanges(null);

        return $entity;
    }

    public function diffEntryToEntity(DiffEntry $entry, SnapshotEntryEntity $existingEntity): SnapshotEntryEntity
    {
        $existingEntity->setDiffKind($entry->diffKind()->value);
        $existingEntity->setTargetUuid(
            null !== $entry->targetUuid()
                ? Uuid::fromString($entry->targetUuid()->toString())
                : null
        );
        $existingEntity->setChanges($entry->changes());

        return $existingEntity;
    }
}
