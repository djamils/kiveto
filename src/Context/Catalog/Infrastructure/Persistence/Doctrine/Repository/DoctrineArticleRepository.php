<?php

declare(strict_types=1);

namespace App\Context\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Catalog\Domain\Article\Article;
use App\Context\Catalog\Domain\Article\Repository\ArticleRepositoryInterface;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleCode;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleId;
use App\Context\Catalog\Domain\Article\ValueObject\Gtin;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\ArticleEntity;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Mapper\ArticleMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineArticleRepository implements ArticleRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private ArticleMapper $mapper,
    ) {
    }

    public function save(Article $article): void
    {
        $uuid     = Uuid::fromString($article->id()->toString());
        $existing = $this->em->find(ArticleEntity::class, $uuid);

        if (null === $existing) {
            $entity = $this->mapper->toEntity($article, null);
            $this->em->persist($entity);
        } else {
            $this->mapper->toEntity($article, $existing);
        }

        $this->em->flush();
    }

    public function findById(ArticleId $id, ClinicId $clinicId): ?Article
    {
        $entity = $this->em->getRepository(ArticleEntity::class)->findOneBy([
            'id'       => Uuid::fromString($id->toString()),
            'tenantId' => Uuid::fromString($clinicId->toString()),
        ]);

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }

    public function findByCode(ArticleCode $code, ClinicId $clinicId): ?Article
    {
        $entity = $this->em->getRepository(ArticleEntity::class)->findOneBy([
            'code'     => $code->toString(),
            'tenantId' => Uuid::fromString($clinicId->toString()),
        ]);

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }

    public function findByGtin(Gtin $gtin, ClinicId $clinicId): ?Article
    {
        $entity = $this->em->getRepository(ArticleEntity::class)->findOneBy([
            'gtin'     => $gtin->toString(),
            'tenantId' => Uuid::fromString($clinicId->toString()),
        ]);

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }

    public function existsByCode(ArticleCode $code, ClinicId $clinicId): bool
    {
        $count = $this->em->getRepository(ArticleEntity::class)->count([
            'code'     => $code->toString(),
            'tenantId' => Uuid::fromString($clinicId->toString()),
        ]);

        return $count > 0;
    }

    public function existsByGtin(Gtin $gtin, ClinicId $clinicId): bool
    {
        $count = $this->em->getRepository(ArticleEntity::class)->count([
            'gtin'     => $gtin->toString(),
            'tenantId' => Uuid::fromString($clinicId->toString()),
        ]);

        return $count > 0;
    }
}
