<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Repository;

use App\System\PharmaceuticalRegistry\Domain\Entity\DiffEntry;
use App\System\PharmaceuticalRegistry\Domain\Entity\SnapshotEntry;
use App\System\PharmaceuticalRegistry\Domain\Repository\SnapshotRepositoryInterface;
use App\System\PharmaceuticalRegistry\Domain\Snapshot;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\DiffKind;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportSource;
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

            // Persist snapshot entries added after the initial save (e.g. by AnmvImporter).
            // Use a COUNT query to avoid loading the full collection into memory.
            $persistedCount = (int) $this->em->createQueryBuilder()
                ->select('COUNT(e.id)')
                ->from(SnapshotEntryEntity::class, 'e')
                ->where('e.snapshot = :snapshot')
                ->setParameter('snapshot', $existing)
                ->getQuery()
                ->getSingleScalarResult()
            ;

            foreach (\array_slice($snapshot->entries(), $persistedCount) as $entry) {
                $entryEntity = $this->mapper->snapshotEntryToEntity($entry, $existing);
                $this->em->persist($entryEntity);
            }

            $this->saveDiffEntries($existing, $snapshot->diffEntries());
        }

        $this->em->flush();
    }

    public function findById(SnapshotId $id): ?Snapshot
    {
        $entity = $this->em->find(SnapshotEntity::class, Uuid::fromString($id->toString()));

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
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
        $snapshotEntity = $this->em->find(SnapshotEntity::class, Uuid::fromString($id->toString()));

        if (null === $snapshotEntity) {
            return;
        }

        $qb = $this->em->createQueryBuilder()
            ->select('e')
            ->from(SnapshotEntryEntity::class, 'e')
            ->where('e.snapshot = :snapshot')
            ->setParameter('snapshot', $snapshotEntity)
            ->getQuery()
        ;

        foreach ($qb->toIterable() as $entryEntity) {
            \assert($entryEntity instanceof SnapshotEntryEntity);
            yield $this->mapper->snapshotEntryToDomain($entryEntity);
            $this->em->detach($entryEntity);
        }
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

        $conn        = $this->em->getConnection();
        $table       = $this->em->getClassMetadata(SnapshotEntryEntity::class)->getTableName();
        $snapshotBin = $snapshot->getId()->toBinary();

        $byKind = [
            DiffKind::CREATE->value   => [],
            DiffKind::UPDATE->value   => [],
            DiffKind::WITHDRAW->value => [],
        ];

        foreach ($diffEntries as $entry) {
            $byKind[$entry->diffKind()->value][] = $entry;
        }

        // CREATE: bulk UPDATE in chunks of 500 — no target_uuid needed
        foreach (array_chunk($byKind[DiffKind::CREATE->value], 500) as $chunk) {
            $ids          = array_map(static fn (DiffEntry $e) => $e->authorityIdentifier(), $chunk);
            $placeholders = implode(',', array_fill(0, \count($ids), '?'));

            $conn->executeStatement(
                "UPDATE {$table} SET diff_kind = ? WHERE snapshot_id = ? AND authority_identifier IN ({$placeholders})",
                array_merge([DiffKind::CREATE->value, $snapshotBin], $ids),
            );
        }

        // UPDATE and WITHDRAW: one row per entry (small numbers in practice)
        foreach ([DiffKind::UPDATE->value, DiffKind::WITHDRAW->value] as $kind) {
            foreach ($byKind[$kind] as $entry) {
                $targetUuidBin = null !== $entry->targetUuid()
                    ? Uuid::fromString($entry->targetUuid()->toString())->toBinary()
                    : null;

                $conn->executeStatement(
                    "UPDATE {$table} SET diff_kind = ?, target_uuid = ? WHERE snapshot_id = ? AND authority_identifier = ?",
                    [$kind, $targetUuidBin, $snapshotBin, $entry->authorityIdentifier()],
                );
            }
        }
    }
}
