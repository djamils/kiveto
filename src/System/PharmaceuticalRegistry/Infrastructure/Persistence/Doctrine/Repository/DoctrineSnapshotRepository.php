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
     * Bulk-updates diff_kind (and target_uuid for UPDATE/WITHDRAW) on existing
     * SnapshotEntry rows using DQL UPDATE chunks of 500.
     * Avoids 3k+ individual findOneBy queries and Doctrine UoW identity-map issues.
     *
     * @param DiffEntry[] $diffEntries
     */
    private function saveDiffEntries(SnapshotEntity $snapshot, array $diffEntries): void
    {
        if ([] === $diffEntries) {
            return;
        }

        $byKind = [
            DiffKind::CREATE->value   => [],
            DiffKind::UPDATE->value   => [],
            DiffKind::WITHDRAW->value => [],
        ];

        foreach ($diffEntries as $entry) {
            $byKind[$entry->diffKind()->value][] = $entry;
        }

        // CREATE: set diff_kind only (no targetUuid)
        foreach (array_chunk($byKind[DiffKind::CREATE->value], 500) as $chunk) {
            $ids = array_map(static fn (DiffEntry $e) => $e->authorityIdentifier(), $chunk);

            $this->em->createQueryBuilder()
                ->update(SnapshotEntryEntity::class, 'e')
                ->set('e.diffKind', ':kind')
                ->where('e.snapshot = :snapshot')
                ->andWhere('e.authorityIdentifier IN (:ids)')
                ->setParameter('kind', DiffKind::CREATE->value)
                ->setParameter('snapshot', $snapshot)
                ->setParameter('ids', $ids)
                ->getQuery()
                ->execute()
            ;
        }

        // UPDATE and WITHDRAW: set diff_kind + targetUuid (one query per entry — small numbers)
        foreach ([DiffKind::UPDATE->value, DiffKind::WITHDRAW->value] as $kind) {
            foreach ($byKind[$kind] as $entry) {
                $targetUuid = null !== $entry->targetUuid()
                    ? Uuid::fromString($entry->targetUuid()->toString())
                    : null;

                $this->em->createQueryBuilder()
                    ->update(SnapshotEntryEntity::class, 'e')
                    ->set('e.diffKind', ':kind')
                    ->set('e.targetUuid', ':targetUuid')
                    ->where('e.snapshot = :snapshot')
                    ->andWhere('e.authorityIdentifier = :id')
                    ->setParameter('kind', $kind)
                    ->setParameter('targetUuid', $targetUuid)
                    ->setParameter('snapshot', $snapshot)
                    ->setParameter('id', $entry->authorityIdentifier())
                    ->getQuery()
                    ->execute()
                ;
            }
        }
    }
}
