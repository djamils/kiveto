<?php

declare(strict_types=1);

namespace App\Translation\Infrastructure\Persistence\Doctrine\Repository;

use App\Translation\Domain\Model\ValueObject\AppScope;
use App\Translation\Domain\Model\ValueObject\Locale;
use App\Translation\Domain\Model\ValueObject\TranslationDomain;
use App\Translation\Domain\Repository\TranslationSearchRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineTranslationSearchRepository implements TranslationSearchRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function findCatalog(AppScope $scope, Locale $locale, TranslationDomain $domain): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('translation_key', 'translation_value')
            ->from('translation_entry')
            ->where('app_scope = :scope')
            ->andWhere('locale = :locale')
            ->andWhere('domain = :domain')
            ->setParameters([
                'scope'  => $scope->value,
                'locale' => $locale->toString(),
                'domain' => $domain->toString(),
            ])
            ->executeQuery()
            ->fetchAllAssociative()
        ;

        $catalog = [];

        foreach ($rows as $row) {
            $catalog[(string) $row['translation_key']] = (string) $row['translation_value'];
        }

        return $catalog;
    }

    public function search(array $criteria, int $page, int $perPage): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('translation_entry')
            ->orderBy('updated_at', 'DESC')
            ->addOrderBy('translation_key', 'ASC')
        ;

        $this->applyCriteria($qb, $criteria);

        $countQb = clone $qb;
        $countQb->resetQueryPart('orderBy')->select('COUNT(*) AS total_count');

        $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage);

        $rows  = $qb->executeQuery()->fetchAllAssociative();
        $total = (int) $countQb->executeQuery()->fetchOne();

        $items = array_map(
            static function (array $row): array {
                return [
                    'scope'     => (string) $row['app_scope'],
                    'locale'    => (string) $row['locale'],
                    'domain'    => (string) $row['domain'],
                    'key'       => (string) $row['translation_key'],
                    'value'     => (string) $row['translation_value'],
                    'updatedAt' => new \DateTimeImmutable((string) $row['updated_at']),
                    'updatedBy' => null !== $row['updated_by'] ? Uuid::fromBinary($row['updated_by'])->toRfc4122() : null,
                ];
            },
            $rows,
        );

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    public function listDomains(?AppScope $scope, ?Locale $locale): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('DISTINCT domain')
            ->from('translation_entry')
            ->orderBy('domain', 'ASC')
        ;

        if (null !== $scope) {
            $qb->andWhere('app_scope = :scope')->setParameter('scope', $scope->value);
        }

        if (null !== $locale) {
            $qb->andWhere('locale = :locale')->setParameter('locale', $locale->toString());
        }

        return array_map(
            static fn (array $row): string => (string) $row['domain'],
            $qb->executeQuery()->fetchAllAssociative(),
        );
    }

    public function listLocales(?AppScope $scope, ?TranslationDomain $domain): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('DISTINCT locale')
            ->from('translation_entry')
            ->orderBy('locale', 'ASC')
        ;

        if (null !== $scope) {
            $qb->andWhere('app_scope = :scope')->setParameter('scope', $scope->value);
        }

        if (null !== $domain) {
            $qb->andWhere('domain = :domain')->setParameter('domain', $domain->toString());
        }

        return array_map(
            static fn (array $row): string => (string) $row['locale'],
            $qb->executeQuery()->fetchAllAssociative(),
        );
    }

    private function applyCriteria(QueryBuilder $qb, array $criteria): void
    {
        if ($criteria['scope'] instanceof AppScope) {
            $qb->andWhere('app_scope = :scope')->setParameter('scope', $criteria['scope']->value);
        }

        if ($criteria['locale'] instanceof Locale) {
            $qb->andWhere('locale = :locale')->setParameter('locale', $criteria['locale']->toString());
        }

        if ($criteria['domain'] instanceof TranslationDomain) {
            $qb->andWhere('domain = :domain')->setParameter('domain', $criteria['domain']->toString());
        }

        if (null !== $criteria['keyContains']) {
            $qb->andWhere('translation_key LIKE :keyContains')->setParameter('keyContains', '%' . $criteria['keyContains'] . '%');
        }

        if (null !== $criteria['valueContains']) {
            $qb->andWhere('translation_value LIKE :valueContains')->setParameter('valueContains', '%' . $criteria['valueContains'] . '%');
        }

        if (null !== $criteria['updatedBy']) {
            $qb->andWhere('updated_by = :updatedBy')->setParameter('updatedBy', Uuid::fromString($criteria['updatedBy'])->toBinary(), Types::BINARY);
        }

        if ($criteria['updatedAfter'] instanceof \DateTimeImmutable) {
            $qb->andWhere('updated_at >= :updatedAfter')->setParameter('updatedAfter', $criteria['updatedAfter']->format('Y-m-d H:i:s.u'));
        }
    }
}
