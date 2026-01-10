<?php

declare(strict_types=1);

namespace App\Translation\Infrastructure\Persistence\Doctrine\Repository;

use App\Translation\Domain\Repository\TranslationSearchRepository;
use App\Translation\Domain\ValueObject\AppScope;
use App\Translation\Domain\ValueObject\Locale;
use App\Translation\Domain\ValueObject\TranslationDomain;
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
            ->from('translation__translation_entries')
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
            $keyRaw   = $row['translation_key'] ?? null;
            $valueRaw = $row['translation_value'] ?? null;

            if (\is_string($keyRaw) && '' !== $keyRaw && \is_string($valueRaw)) {
                $catalog[$keyRaw] = $valueRaw;
            }
        }

        return $catalog;
    }

    /**
     * @param array{
     *     scope?: AppScope|null,
     *     locale?: Locale|null,
     *     domain?: TranslationDomain|null,
     *     keyContains?: string|null,
     *     valueContains?: string|null,
     *     updatedBy?: string|null,
     *     updatedAfter?: \DateTimeImmutable|null,
     *     createdBy?: string|null,
     *     createdAfter?: \DateTimeImmutable|null
     * } $criteria
     *
     * @return array{
     *     items: list<array{
     *         scope: string,
     *         locale: string,
     *         domain: string,
     *         key: string,
     *         value: string,
     *         description: string|null,
     *         createdAt: \DateTimeImmutable,
     *         createdBy: string|null,
     *         updatedAt: \DateTimeImmutable,
     *         updatedBy: string|null
     *     }>,
     *     total: int
     * }
     */
    public function search(array $criteria, int $page, int $perPage): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('translation__translation_entries.*')
            ->from('translation__translation_entries')
            ->orderBy('updated_at', 'DESC')
            ->addOrderBy('translation_key', 'ASC')
        ;

        $this->applyCriteria($qb, $criteria);

        $countQb = $this->connection->createQueryBuilder()
            ->select('COUNT(*) AS total_count')
            ->from('translation__translation_entries')
        ;
        $this->applyCriteria($countQb, $criteria);

        $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage);

        $rows = $qb->executeQuery()->fetchAllAssociative();

        $totalRaw = $countQb->executeQuery()->fetchOne();
        $total    = \is_int($totalRaw) ? $totalRaw : (is_numeric($totalRaw) ? (int) $totalRaw : 0);

        $items = array_map(
            static function (array $row): array {
                return [
                    'scope'  => \is_string($row['app_scope'] ?? null) ? (string) $row['app_scope'] : '',
                    'locale' => \is_string($row['locale'] ?? null) ? (string) $row['locale'] : '',
                    'domain' => \is_string($row['domain'] ?? null) ? (string) $row['domain'] : '',
                    'key'    => \is_string($row['translation_key'] ?? null)
                        ? (string) $row['translation_key']
                        : '',
                    'value' => \is_string($row['translation_value'] ?? null)
                        ? (string) $row['translation_value']
                        : '',
                    'description' => \is_string($row['description'] ?? null) ? (string) $row['description'] : null,
                    'createdAt'   => new \DateTimeImmutable(
                        \is_string($row['created_at'] ?? null) ? (string) $row['created_at'] : 'now',
                    ),
                    'createdBy' => null !== ($row['created_by'] ?? null) && \is_string($row['created_by'])
                        ? Uuid::fromBinary($row['created_by'])->toRfc4122()
                        : null,
                    'updatedAt' => new \DateTimeImmutable(
                        \is_string($row['updated_at'] ?? null) ? (string) $row['updated_at'] : 'now',
                    ),
                    'updatedBy' => null !== ($row['updated_by'] ?? null) && \is_string($row['updated_by'])
                        ? Uuid::fromBinary($row['updated_by'])->toRfc4122()
                        : null,
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
            ->from('translation__translation_entries')
            ->orderBy('domain', 'ASC')
        ;

        if (null !== $scope) {
            $qb->andWhere('app_scope = :scope')->setParameter('scope', $scope->value);
        }

        if (null !== $locale) {
            $qb->andWhere('locale = :locale')->setParameter('locale', $locale->toString());
        }

        return array_map(
            static fn (array $row): string => \is_string($row['domain'] ?? null) ? (string) $row['domain'] : '',
            $qb->executeQuery()->fetchAllAssociative(),
        );
    }

    public function listLocales(?AppScope $scope, ?TranslationDomain $domain): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('DISTINCT locale')
            ->from('translation__translation_entries')
            ->orderBy('locale', 'ASC')
        ;

        if (null !== $scope) {
            $qb->andWhere('app_scope = :scope')->setParameter('scope', $scope->value);
        }

        if (null !== $domain) {
            $qb->andWhere('domain = :domain')->setParameter('domain', $domain->toString());
        }

        return array_map(
            static fn (array $row): string => \is_string($row['locale'] ?? null) ? (string) $row['locale'] : '',
            $qb->executeQuery()->fetchAllAssociative(),
        );
    }

    /**
     * @param array{
     *     scope?: AppScope|null,
     *     locale?: Locale|null,
     *     domain?: TranslationDomain|null,
     *     keyContains?: string|null,
     *     valueContains?: string|null,
     *     updatedBy?: string|null,
     *     updatedAfter?: \DateTimeImmutable|null,
     *     createdBy?: string|null,
     *     createdAfter?: \DateTimeImmutable|null
     * } $criteria
     */
    private function applyCriteria(QueryBuilder $qb, array $criteria): void
    {
        if (($criteria['scope'] ?? null) instanceof AppScope) {
            $qb
                ->andWhere('app_scope = :scope')
                ->setParameter('scope', $criteria['scope']->value)
            ;
        }

        if (($criteria['locale'] ?? null) instanceof Locale) {
            $qb
                ->andWhere('locale = :locale')
                ->setParameter('locale', $criteria['locale']->toString())
            ;
        }

        if (($criteria['domain'] ?? null) instanceof TranslationDomain) {
            $qb
                ->andWhere('domain = :domain')
                ->setParameter('domain', $criteria['domain']->toString())
            ;
        }

        if (\is_string($criteria['keyContains'] ?? null)) {
            $qb
                ->andWhere('translation_key LIKE :keyContains')
                ->setParameter('keyContains', '%' . $criteria['keyContains'] . '%')
            ;
        }

        if (\is_string($criteria['valueContains'] ?? null)) {
            $qb
                ->andWhere('translation_value LIKE :valueContains')
                ->setParameter('valueContains', '%' . $criteria['valueContains'] . '%')
            ;
        }

        $updatedBy = $criteria['updatedBy'] ?? null;
        if (\is_string($updatedBy)) {
            $qb
                ->andWhere('updated_by = :updatedBy')
                ->setParameter('updatedBy', Uuid::fromString($updatedBy)->toBinary(), Types::BINARY)
            ;
        }

        $updatedAfter = $criteria['updatedAfter'] ?? null;
        if ($updatedAfter instanceof \DateTimeImmutable) {
            $qb
                ->andWhere('updated_at >= :updatedAfter')
                ->setParameter('updatedAfter', $updatedAfter->format('Y-m-d H:i:s.u'))
            ;
        }

        $createdBy = $criteria['createdBy'] ?? null;
        if (\is_string($createdBy)) {
            $qb
                ->andWhere('created_by = :createdBy')
                ->setParameter('createdBy', Uuid::fromString($createdBy)->toBinary(), Types::BINARY)
            ;
        }

        $createdAfter = $criteria['createdAfter'] ?? null;
        if ($createdAfter instanceof \DateTimeImmutable) {
            $qb
                ->andWhere('created_at >= :createdAfter')
                ->setParameter('createdAfter', $createdAfter->format('Y-m-d H:i:s.u'))
            ;
        }
    }
}
