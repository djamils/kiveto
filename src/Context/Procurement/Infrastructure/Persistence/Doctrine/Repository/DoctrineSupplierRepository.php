<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Procurement\Domain\Supplier\Repository\SupplierRepositoryInterface;
use App\Context\Procurement\Domain\Supplier\Supplier;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierCode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\SupplierEntity;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Mapper\SupplierMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineSupplierRepository implements SupplierRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private SupplierMapper $mapper,
    ) {
    }

    public function save(Supplier $supplier): void
    {
        $entity = $this->mapper->toEntity($supplier);
        $this->em->persist($entity);
        $this->em->flush();
    }

    public function findById(SupplierId $id): ?Supplier
    {
        $entity = $this->em->find(SupplierEntity::class, Uuid::fromString($id->toString()));

        return null !== $entity ? $this->mapper->toDomain($entity) : null;
    }

    public function findByCode(SupplierCode $code): ?Supplier
    {
        $entity = $this->em->getRepository(SupplierEntity::class)->findOneBy([
            'code' => $code->toString(),
        ]);

        return null !== $entity ? $this->mapper->toDomain($entity) : null;
    }

    /** @return list<Supplier> */
    public function findAll(): array
    {
        $entities = $this->em->getRepository(SupplierEntity::class)->findAll();

        return array_map(fn (SupplierEntity $e): Supplier => $this->mapper->toDomain($e), $entities);
    }
}
