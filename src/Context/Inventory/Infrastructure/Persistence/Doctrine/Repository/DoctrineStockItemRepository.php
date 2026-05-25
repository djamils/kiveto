<?php

declare(strict_types=1);

namespace App\Context\Inventory\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Inventory\Domain\Shared\ValueObject\ArticleId;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockItem\Exception\ConcurrentStockModificationException;
use App\Context\Inventory\Domain\StockItem\Repository\StockItemRepositoryInterface;
use App\Context\Inventory\Domain\StockItem\StockItem;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemId;
use App\Context\Inventory\Infrastructure\Persistence\Doctrine\Entity\StockItemEntity;
use App\Context\Inventory\Infrastructure\Persistence\Doctrine\Mapper\StockItemMapper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineStockItemRepository implements StockItemRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private StockItemMapper $mapper,
    ) {
    }

    public function save(StockItem $stockItem): void
    {
        try {
            $entity = $this->mapper->toEntity($stockItem);
            $this->em->persist($entity);
            $this->em->flush();
        } catch (OptimisticLockException) {
            throw new ConcurrentStockModificationException($stockItem->id()->toString());
        }
    }

    public function findById(StockItemId $id, ClinicId $clinicId): ?StockItem
    {
        $entity = $this->em->getRepository(StockItemEntity::class)->findOneBy([
            'id'       => Uuid::fromString($id->toString()),
            'clinicId' => Uuid::fromString($clinicId->toString()),
        ]);

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }

    public function findByClinicAndArticle(ClinicId $clinicId, ArticleId $articleId): ?StockItem
    {
        $entity = $this->em->getRepository(StockItemEntity::class)->findOneBy([
            'clinicId'  => Uuid::fromString($clinicId->toString()),
            'articleId' => Uuid::fromString($articleId->toString()),
        ]);

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }
}
