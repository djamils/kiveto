<?php

declare(strict_types=1);

namespace App\Context\Inventory\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockMovement\Repository\StockMovementRepositoryInterface;
use App\Context\Inventory\Domain\StockMovement\StockMovement;
use App\Context\Inventory\Domain\StockMovement\ValueObject\MovementReason;
use App\Context\Inventory\Domain\StockMovement\ValueObject\MovementType;
use App\Context\Inventory\Domain\StockMovement\ValueObject\StockMovementId;
use App\Context\Inventory\Infrastructure\Persistence\Doctrine\Entity\StockMovementEntity;
use App\Context\Inventory\Infrastructure\Persistence\Doctrine\Mapper\StockMovementMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineStockMovementRepository implements StockMovementRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private StockMovementMapper $mapper,
    ) {
    }

    public function save(StockMovement $movement): void
    {
        $entity = $this->mapper->toEntity($movement);
        $this->em->persist($entity);
        $this->em->flush();
    }

    public function findById(StockMovementId $id): ?StockMovement
    {
        $entity = $this->em->getRepository(StockMovementEntity::class)->find(
            Uuid::fromString($id->toString())
        );

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }

    /**
     * @return list<StockMovement>
     */
    public function findByClinic(
        ClinicId $clinicId,
        ?\DateTimeImmutable $from,
        ?\DateTimeImmutable $to,
        ?MovementType $type,
        ?MovementReason $reason,
        int $limit,
        int $offset,
    ): array {
        $qb = $this->em->createQueryBuilder()
            ->select('m')
            ->from(StockMovementEntity::class, 'm')
            ->where('m.clinicId = :clinicId')
            ->setParameter('clinicId', Uuid::fromString($clinicId->toString()))
            ->orderBy('m.occurredAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
        ;

        if (null !== $from) {
            $qb->andWhere('m.occurredAt >= :from')
               ->setParameter('from', $from)
            ;
        }

        if (null !== $to) {
            $qb->andWhere('m.occurredAt <= :to')
               ->setParameter('to', $to)
            ;
        }

        if (null !== $type) {
            $qb->andWhere('m.type = :type')
               ->setParameter('type', $type->value)
            ;
        }

        if (null !== $reason) {
            $qb->andWhere('m.reason = :reason')
               ->setParameter('reason', $reason->value)
            ;
        }

        /** @var list<StockMovementEntity> $entities */
        $entities = $qb->getQuery()->getResult();

        return array_map(fn (StockMovementEntity $e): StockMovement => $this->mapper->toDomain($e), $entities);
    }
}
