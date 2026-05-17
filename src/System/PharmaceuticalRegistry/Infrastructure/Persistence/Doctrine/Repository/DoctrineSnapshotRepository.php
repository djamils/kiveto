<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Repository;

use App\System\PharmaceuticalRegistry\Domain\Entity\DiffEntry;
use App\System\PharmaceuticalRegistry\Domain\Entity\SnapshotEntry;
use App\System\PharmaceuticalRegistry\Domain\Repository\SnapshotRepositoryInterface;
use App\System\PharmaceuticalRegistry\Domain\Snapshot;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ContentHash;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\DiffKind;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportSource;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\MarketingAuthorizationId;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\SnapshotId;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\SnapshotEntity;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\SnapshotEntryEntity;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Mapper\SnapshotMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineSnapshotRepository implements SnapshotRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SnapshotMapper $mapper,
    ) {
    }

    public function save(Snapshot $snapshot): void
    {
        $snapshotId = Uuid::fromString($snapshot->id()->toString());
        $existing   = $this->em->find(SnapshotEntity::class, $snapshotId);

        if (null === $existing) {
            $entity = $this->mapper->toEntity($snapshot);
            $this->em->persist($entity);

            foreach ($snapshot->entries() as $entry) {
                $entryEntity = $this->mapper->snapshotEntryToEntity($entry, $entity);
                $this->em->persist($entryEntity);
            }
        } else {
            $existing->setStatus($snapshot->status()->value);
            $existing->setAppliedAt($snapshot->appliedAt());
            $existing->setErrorMessage($snapshot->errorMessage());

            // Count persisted entries via DBAL + UNHEX to avoid ORM binary UUID comparison issue.
            $snapshotUuidHex   = str_replace('-', '', $snapshot->id()->toString());
            $table             = $this->em->getClassMetadata(SnapshotEntryEntity::class)->getTableName();
            $persistedCountRaw = $this->em->getConnection()->executeQuery(
                "SELECT COUNT(*) FROM {$table} WHERE snapshot_id = UNHEX(?)",
                [$snapshotUuidHex],
            )->fetchOne();
            $persistedCount = is_numeric($persistedCountRaw) ? (int) $persistedCountRaw : 0;

            foreach (\array_slice($snapshot->entries(), $persistedCount) as $entry) {
                $entryEntity = $this->mapper->snapshotEntryToEntity($entry, $existing);
                $this->em->persist($entryEntity);
            }

            $this->saveDiffEntries($existing, $snapshot->diffEntries());
        }

        $this->em->flush();
    }

    public function findById(SnapshotId $id, bool $withDiffEntries = true): ?Snapshot
    {
        $entity = $this->em->find(SnapshotEntity::class, Uuid::fromString($id->toString()));

        if (null === $entity) {
            return null;
        }

        $diffEntries = $withDiffEntries ? $this->loadDiffEntries($id->toString()) : [];

        return $this->mapper->toDomain($entity, $diffEntries);
    }

    /**
     * @return Snapshot[]
     */
    public function findRecent(ImportSource $source, int $limit): array
    {
        $entities = $this->em->getRepository(SnapshotEntity::class)
            ->findBy(['source' => $source->value], ['downloadedAt' => 'DESC'], $limit)
        ;

        return array_map(fn (SnapshotEntity $e) => $this->mapper->toDomain($e), $entities);
    }

    /**
     * @return iterable<SnapshotEntry>
     */
    public function streamEntriesForDiff(SnapshotId $id): iterable
    {
        // Use raw DBAL + UNHEX to avoid the ORM binary UUID parameter encoding issue:
        // Doctrine passes toBinary() as PARAM_STR which MySQL mishandles when the
        // binary bytes form invalid UTF-8 sequences.
        $snapshotUuidHex = str_replace('-', '', $id->toString());
        $table           = $this->em->getClassMetadata(SnapshotEntryEntity::class)->getTableName();
        $conn            = $this->em->getConnection();

        $stmt = $conn->executeQuery(
            "SELECT authority_identifier, content_hash, raw_dto FROM {$table} WHERE snapshot_id = UNHEX(?)",
            [$snapshotUuidHex],
        );

        foreach ($stmt->iterateAssociative() as $row) {
            \assert(\is_string($row['authority_identifier']));
            \assert(\is_string($row['content_hash']));
            \assert(\is_string($row['raw_dto']));

            /** @var array<mixed> $rawDto */
            $rawDto = json_decode($row['raw_dto'], true) ?? [];

            yield new SnapshotEntry(
                authorityIdentifier: $row['authority_identifier'],
                contentHash: ContentHash::fromString($row['content_hash']),
                rawDto: $rawDto,
            );
        }
    }

    /**
     * Loads diff entries via raw DBAL + UNHEX to avoid ORM binary UUID comparison issue.
     *
     * @return DiffEntry[]
     */
    private function loadDiffEntries(string $snapshotUuid): array
    {
        $snapshotUuidHex = str_replace('-', '', $snapshotUuid);
        $table           = $this->em->getClassMetadata(SnapshotEntryEntity::class)->getTableName();

        $rows = $this->em->getConnection()->executeQuery(
            'SELECT authority_identifier, diff_kind, target_uuid, changes, raw_dto'
            . " FROM {$table}"
            . ' WHERE snapshot_id = UNHEX(?) AND diff_kind IS NOT NULL',
            [$snapshotUuidHex],
        )->fetchAllAssociative();

        $diffEntries = [];

        foreach ($rows as $row) {
            \assert(\is_string($row['authority_identifier']));
            \assert(\is_string($row['diff_kind']));

            $targetUuid = null;

            if (null !== $row['target_uuid']) {
                \assert(\is_string($row['target_uuid']));
                $targetUuid = MarketingAuthorizationId::fromString(
                    \sprintf(
                        '%s-%s-%s-%s-%s',
                        bin2hex(substr($row['target_uuid'], 0, 4)),
                        bin2hex(substr($row['target_uuid'], 4, 2)),
                        bin2hex(substr($row['target_uuid'], 6, 2)),
                        bin2hex(substr($row['target_uuid'], 8, 2)),
                        bin2hex(substr($row['target_uuid'], 10, 6)),
                    ),
                );
            }

            /** @var array<mixed>|null $rawDto */
            $rawDto = null !== $row['raw_dto'] && \is_string($row['raw_dto'])
                ? json_decode($row['raw_dto'], true)
                : null;

            $diffEntries[] = new DiffEntry(
                authorityIdentifier: $row['authority_identifier'],
                diffKind: DiffKind::from($row['diff_kind']),
                targetUuid: $targetUuid,
                rawDto: $rawDto,
            );
        }

        return $diffEntries;
    }

    /**
     * Bulk-updates diff_kind (and target_uuid for UPDATE/WITHDRAW) via raw DBAL.
     * Bypasses Doctrine ORM entirely to avoid DQL IN-parameter expansion issues
     * and UoW identity-map interference after mass detach().
     *
     * @param DiffEntry[] $diffEntries
     */
    private function saveDiffEntries(SnapshotEntity $snapshot, array $diffEntries): void
    {
        if ([] === $diffEntries) {
            return;
        }

        $conn            = $this->em->getConnection();
        $table           = $this->em->getClassMetadata(SnapshotEntryEntity::class)->getTableName();
        $snapshotUuidHex = str_replace('-', '', $snapshot->getId()->toRfc4122());

        $byKind = [
            DiffKind::CREATE->value   => [],
            DiffKind::UPDATE->value   => [],
            DiffKind::WITHDRAW->value => [],
        ];

        foreach ($diffEntries as $entry) {
            $byKind[$entry->diffKind()->value][] = $entry;
        }

        // CREATE: bulk UPDATE in chunks of 500 via UNHEX() to avoid binary param encoding issues
        foreach (array_chunk($byKind[DiffKind::CREATE->value], 500) as $chunk) {
            $ids          = array_map(static fn (DiffEntry $e) => $e->authorityIdentifier(), $chunk);
            $placeholders = implode(',', array_fill(0, \count($ids), '?'));

            $conn->executeStatement(
                "UPDATE {$table} SET diff_kind = ? WHERE snapshot_id = UNHEX(?) AND authority_identifier IN ({$placeholders})",
                array_merge([DiffKind::CREATE->value, $snapshotUuidHex], $ids),
            );
        }

        // UPDATE and WITHDRAW: one query per entry (small numbers in practice)
        foreach ([DiffKind::UPDATE->value, DiffKind::WITHDRAW->value] as $kind) {
            foreach ($byKind[$kind] as $entry) {
                $targetUuidHex = null !== $entry->targetUuid()
                    ? str_replace('-', '', $entry->targetUuid()->toString())
                    : null;

                $conn->executeStatement(
                    "UPDATE {$table} SET diff_kind = ?, target_uuid = UNHEX(?) WHERE snapshot_id = UNHEX(?) AND authority_identifier = ?",
                    [$kind, $targetUuidHex, $snapshotUuidHex, $entry->authorityIdentifier()],
                );
            }
        }
    }
}
