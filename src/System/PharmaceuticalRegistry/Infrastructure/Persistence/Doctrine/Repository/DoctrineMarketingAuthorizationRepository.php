<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Repository;

use App\System\PharmaceuticalRegistry\Domain\MarketingAuthorization;
use App\System\PharmaceuticalRegistry\Domain\Repository\MarketingAuthorizationHashProjection;
use App\System\PharmaceuticalRegistry\Domain\Repository\MarketingAuthorizationRepositoryInterface;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ContentHash;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportSource;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\MarketingAuthorizationId;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\ActiveSubstanceEntity;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\AuthorizationEntity;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\JurisdictionEntity;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Mapper\MarketingAuthorizationMapper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Symfony\Component\Uid\Uuid;

final class DoctrineMarketingAuthorizationRepository implements MarketingAuthorizationRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MarketingAuthorizationMapper $mapper,
    ) {
    }

    public function save(MarketingAuthorization $ma): void
    {
        $id       = Uuid::fromString($ma->id()->toString());
        $existing = $this->em->find(AuthorizationEntity::class, $id);

        if (null === $existing) {
            $activeSubstanceMap = $this->buildActiveSubstanceMap($ma);
            $entity             = $this->mapper->toEntity($ma, $activeSubstanceMap);
            $this->em->persist($entity);
        } else {
            $existing->setCommercialName($ma->commercialName()->toString());
            $existing->setHolderLaboratory($ma->holderLaboratory()->toString());
            $existing->setStatus($ma->status()->value);
            $existing->setAuthorizationDate($ma->authorizationDate()->toDateTime());
            $existing->setNature($ma->nature()->value);
            $existing->setPharmaceuticalForm($ma->pharmaceuticalForm()->toString());
            $existing->setAtcVetCode($ma->atcVetCode()?->toString());
            $existing->setPermanentIdentifier($ma->permanentIdentifier()?->toString());
            $existing->setContentHash($ma->contentHash());
            $existing->setLastImportSource($ma->lastImportSource()->value);
            $existing->setLastImportedAt($ma->lastImportedAt());
            $existing->setUpdatedAt($ma->updatedAt());

            $cs = $ma->controlledSubstance();

            if (null !== $cs) {
                $existing->setControlledSubstanceClass($cs->class()->value);
                $existing->setControlledSubstanceJurisdiction($cs->jurisdictionalCode()->jurisdictionCode()->toString());
                $existing->setControlledSubstanceCode($cs->jurisdictionalCode()->code());
                $existing->setControlledSubstanceRestrictions($cs->restrictions());
            } else {
                $existing->setControlledSubstanceClass(null);
                $existing->setControlledSubstanceJurisdiction(null);
                $existing->setControlledSubstanceCode(null);
                $existing->setControlledSubstanceRestrictions(null);
            }
        }

        $this->em->flush();
    }

    public function findById(MarketingAuthorizationId $id): ?MarketingAuthorization
    {
        $entity = $this->em->find(AuthorizationEntity::class, Uuid::fromString($id->toString()));

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain(
            entity: $entity,
            jurisdictions: $entity->getJurisdictions()->toArray(),
            presentations: $entity->getPresentations()->toArray(),
            compositions: $entity->getCompositions()->toArray(),
            targetUsages: $entity->getTargetUsages()->toArray(),
            summary: $entity->getSummary(),
        );
    }

    public function findByJurisdictionalId(
        string $jurisdictionCode,
        string $authorityCode,
        string $authorityIdentifier,
    ): ?MarketingAuthorization {
        $qb = $this->em->createQueryBuilder()
            ->select('a')
            ->from(AuthorizationEntity::class, 'a')
            ->join(JurisdictionEntity::class, 'j', 'WITH', 'j.authorization = a')
            ->where('j.jurisdictionCode = :jurisdictionCode')
            ->andWhere('j.authorityCode = :authorityCode')
            ->andWhere('j.authorityIdentifier = :authorityIdentifier')
            ->setParameter('jurisdictionCode', $jurisdictionCode)
            ->setParameter('authorityCode', $authorityCode)
            ->setParameter('authorityIdentifier', $authorityIdentifier)
        ;

        $entity = $qb->getQuery()->getOneOrNullResult();

        if (null === $entity) {
            return null;
        }

        \assert($entity instanceof AuthorizationEntity);

        return $this->mapper->toDomain(
            entity: $entity,
            jurisdictions: $entity->getJurisdictions()->toArray(),
            presentations: $entity->getPresentations()->toArray(),
            compositions: $entity->getCompositions()->toArray(),
            targetUsages: $entity->getTargetUsages()->toArray(),
            summary: $entity->getSummary(),
        );
    }

    /**
     * @return iterable<MarketingAuthorizationHashProjection>
     */
    public function findProjectionsBySource(ImportSource $source): iterable
    {
        $conn   = $this->em->getConnection();
        $rsm    = new ResultSetMapping();
        $table  = $this->resolveAuthorizationTable();
        $jTable = $this->resolveJurisdictionTable();

        $sql = 'SELECT BIN_TO_UUID(a.id) AS auth_id, a.content_hash, j.authority_identifier'
            . " FROM {$table} a"
            . " JOIN {$jTable} j ON j.authorization_id = a.id"
            . ' WHERE a.last_import_source = ?';

        $result = $conn->executeQuery($sql, [$source->value]);

        foreach ($result->iterateAssociative() as $row) {
            \assert(\is_string($row['authority_identifier']));
            \assert(\is_string($row['content_hash']));
            \assert(\is_string($row['auth_id']));

            yield new MarketingAuthorizationHashProjection(
                authorityIdentifier: $row['authority_identifier'],
                contentHash: ContentHash::fromString($row['content_hash']),
                id: MarketingAuthorizationId::fromString($row['auth_id']),
            );
        }
    }

    private function resolveAuthorizationTable(): string
    {
        return $this->em->getClassMetadata(AuthorizationEntity::class)->getTableName();
    }

    private function resolveJurisdictionTable(): string
    {
        return $this->em->getClassMetadata(JurisdictionEntity::class)->getTableName();
    }

    /** @return array<string, ActiveSubstanceEntity> */
    private function buildActiveSubstanceMap(MarketingAuthorization $ma): array
    {
        $ids = [];

        foreach ($ma->compositions() as $composition) {
            $ids[] = Uuid::fromString($composition->activeSubstanceId()->toString());
        }

        if ([] === $ids) {
            return [];
        }

        $entities = $this->em->getRepository(ActiveSubstanceEntity::class)
            ->findBy(['id' => $ids])
        ;

        $map = [];

        foreach ($entities as $entity) {
            $map[$entity->getId()->toString()] = $entity;
        }

        return $map;
    }
}
