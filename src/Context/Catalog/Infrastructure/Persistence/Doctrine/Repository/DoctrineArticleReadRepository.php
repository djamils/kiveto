<?php

declare(strict_types=1);

namespace App\Context\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Catalog\Application\Port\ArticleReadRepositoryInterface;
use App\Context\Catalog\Domain\Article\Article;
use App\Context\Catalog\Domain\Article\ValueObject\MarketingAuthorizationRef;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\ArticleEntity;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Mapper\ArticleMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineArticleReadRepository implements ArticleReadRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private ArticleMapper $mapper,
    ) {
    }

    /** @return list<Article> */
    public function findAllByAuthorizationRef(MarketingAuthorizationRef $ref): array
    {
        $entities = $this->em->getRepository(ArticleEntity::class)->findBy([
            'drugAuthRef' => Uuid::fromString($ref->toString()),
        ]);

        return array_map(fn (ArticleEntity $e) => $this->mapper->toDomain($e), $entities);
    }

    /** @return list<Article> */
    public function search(string $term, ClinicId $clinicId, int $limit): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('a')
            ->from(ArticleEntity::class, 'a')
            ->where('a.tenantId = :tenantId')
            ->andWhere('a.name LIKE :term')
            ->setParameter('tenantId', Uuid::fromString($clinicId->toString()))
            ->setParameter('term', '%' . $term . '%')
            ->setMaxResults($limit)
        ;

        /** @var list<ArticleEntity> $entities */
        $entities = $qb->getQuery()->getResult();

        return array_map(fn (ArticleEntity $e) => $this->mapper->toDomain($e), $entities);
    }
}
