<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Repository;

use App\System\PharmaceuticalRegistry\Domain\Entity\SnapshotEntry;
use App\System\PharmaceuticalRegistry\Domain\Repository\SnapshotRepositoryInterface;
use App\System\PharmaceuticalRegistry\Domain\Snapshot;
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

            foreach ($snapshot->diffEntries() as $diffEntry) {
                $entryRepo   = $this->em->getRepository(SnapshotEntryEntity::class);
                $entryEntity = $entryRepo->findOneBy([
                    'snapshot'            => $existing,
                    'authorityIdentifier' => $diffEntry->authorityIdentifier(),
                ]);

                if (null !== $entryEntity) {
                    $this->mapper->diffEntryToEntity($diffEntry, $entryEntity);
                } else {
                    $newEntry = new SnapshotEntryEntity();
                    $newEntry->setId(Uuid::v7());
                    $newEntry->setSnapshot($existing);
                    $newEntry->setAuthorityIdentifier($diffEntry->authorityIdentifier());
                    $newEntry->setContentHash('');
                    $newEntry->setRawDto($diffEntry->rawDto() ?? []);
                    $newEntry->setDiffKind($diffEntry->diffKind()->value);
                    $newEntry->setTargetUuid(
                        null !== $diffEntry->targetUuid()
                            ? Uuid::fromString($diffEntry->targetUuid()->toString())
                            : null
                    );
                    $newEntry->setChanges($diffEntry->changes());
                    $this->em->persist($newEntry);
                }
            }
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
}
