<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Repository;

use App\System\PharmaceuticalRegistry\Application\Port\MarketingAuthorizationSearchRepositoryInterface;
use App\System\PharmaceuticalRegistry\Application\Query\SearchMarketingAuthorizations\MarketingAuthorizationSearchResult;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ActiveSubstanceId;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\TargetSpeciesCode;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\AuthorizationEntity;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\CompositionEntity;
use App\System\PharmaceuticalRegistry\Infrastructure\Persistence\Doctrine\Entity\TargetUsageEntity;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineMarketingAuthorizationSearchRepository implements MarketingAuthorizationSearchRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return MarketingAuthorizationSearchResult[]
     */
    public function search(string $term, int $limit, array $filters = []): array
    {
        $conn  = $this->em->getConnection();
        $table = $this->em->getClassMetadata(AuthorizationEntity::class)->getTableName();

        // Every word typed must start a word of the name, so a picker finds
        // « Apoquel » from « apo ». LIKE rather than the full-text index: it
        // sees rows written in the current transaction too.
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($term), -1, \PREG_SPLIT_NO_EMPTY) ?: [];

        if ([] === $words) {
            return [];
        }

        $where  = [];
        $params = [];
        $types  = [];

        foreach ($words as $word) {
            $where[]  = '(LOWER(a.commercial_name) LIKE ? OR LOWER(a.commercial_name) LIKE ?)';
            $params[] = $word . '%';
            $params[] = '% ' . $word . '%';
            $types[]  = ParameterType::STRING;
            $types[]  = ParameterType::STRING;
        }

        $params[] = max(1, $limit);
        $types[]  = ParameterType::INTEGER;

        $sql = 'SELECT BIN_TO_UUID(a.id) AS id, a.commercial_name, a.status, a.atc_vet_code, a.holder_laboratory'
            . " FROM {$table} a"
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY a.commercial_name'
            . ' LIMIT ?';

        $rows = $conn->executeQuery($sql, $params, $types)->fetchAllAssociative();

        return array_map(
            static function (array $row): MarketingAuthorizationSearchResult {
                \assert(\is_string($row['id']));
                \assert(\is_string($row['commercial_name']));
                \assert(\is_string($row['status']));
                \assert(null === $row['atc_vet_code'] || \is_string($row['atc_vet_code']));
                \assert(\is_string($row['holder_laboratory']));

                return new MarketingAuthorizationSearchResult(
                    id: $row['id'],
                    commercialName: $row['commercial_name'],
                    status: $row['status'],
                    atcVetCode: $row['atc_vet_code'],
                    gtin: null,
                    holderLaboratory: $row['holder_laboratory'],
                );
            },
            $rows,
        );
    }

    /**
     * @return MarketingAuthorizationSearchResult[]
     */
    public function listBySubstance(ActiveSubstanceId $activeSubstanceId): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('DISTINCT a.id, a.commercialName, a.status, a.atcVetCode, a.holderLaboratory')
            ->from(AuthorizationEntity::class, 'a')
            ->join(CompositionEntity::class, 'c', 'WITH', 'c.authorization = a')
            ->where('IDENTITY(c.activeSubstance) = :substanceId')
            ->setParameter('substanceId', Uuid::fromString($activeSubstanceId->toString()))
        ;

        return array_map(
            static function (mixed $row): MarketingAuthorizationSearchResult {
                \assert(\is_array($row));
                \assert($row['id'] instanceof Uuid);
                \assert(\is_string($row['commercialName']));
                \assert(\is_string($row['status']));
                \assert(null === $row['atcVetCode'] || \is_string($row['atcVetCode']));
                \assert(\is_string($row['holderLaboratory']));

                return new MarketingAuthorizationSearchResult(
                    id: $row['id']->toString(),
                    commercialName: $row['commercialName'],
                    status: $row['status'],
                    atcVetCode: $row['atcVetCode'],
                    gtin: null,
                    holderLaboratory: $row['holderLaboratory'],
                );
            },
            $qb->getQuery()->getArrayResult(),
        );
    }

    /**
     * @return MarketingAuthorizationSearchResult[]
     */
    public function listByAtcVet(string $codeOrGroup): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('a.id, a.commercialName, a.status, a.atcVetCode, a.holderLaboratory')
            ->from(AuthorizationEntity::class, 'a')
            ->where('a.atcVetCode LIKE :code')
            ->setParameter('code', $codeOrGroup . '%')
        ;

        return array_map(
            static function (mixed $row): MarketingAuthorizationSearchResult {
                \assert(\is_array($row));
                \assert($row['id'] instanceof Uuid);
                \assert(\is_string($row['commercialName']));
                \assert(\is_string($row['status']));
                \assert(null === $row['atcVetCode'] || \is_string($row['atcVetCode']));
                \assert(\is_string($row['holderLaboratory']));

                return new MarketingAuthorizationSearchResult(
                    id: $row['id']->toString(),
                    commercialName: $row['commercialName'],
                    status: $row['status'],
                    atcVetCode: $row['atcVetCode'],
                    gtin: null,
                    holderLaboratory: $row['holderLaboratory'],
                );
            },
            $qb->getQuery()->getArrayResult(),
        );
    }

    /**
     * @return MarketingAuthorizationSearchResult[]
     */
    public function listByTargetSpecies(TargetSpeciesCode $speciesCode): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('DISTINCT a.id, a.commercialName, a.status, a.atcVetCode, a.holderLaboratory')
            ->from(AuthorizationEntity::class, 'a')
            ->join(TargetUsageEntity::class, 't', 'WITH', 't.authorization = a')
            ->where('t.targetSpeciesCode = :speciesCode')
            ->setParameter('speciesCode', $speciesCode->toString())
        ;

        return array_map(
            static function (mixed $row): MarketingAuthorizationSearchResult {
                \assert(\is_array($row));
                \assert($row['id'] instanceof Uuid);
                \assert(\is_string($row['commercialName']));
                \assert(\is_string($row['status']));
                \assert(null === $row['atcVetCode'] || \is_string($row['atcVetCode']));
                \assert(\is_string($row['holderLaboratory']));

                return new MarketingAuthorizationSearchResult(
                    id: $row['id']->toString(),
                    commercialName: $row['commercialName'],
                    status: $row['status'],
                    atcVetCode: $row['atcVetCode'],
                    gtin: null,
                    holderLaboratory: $row['holderLaboratory'],
                );
            },
            $qb->getQuery()->getArrayResult(),
        );
    }
}
