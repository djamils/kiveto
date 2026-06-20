<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierAccount\Repository\SupplierAccountRepositoryInterface;
use App\Context\Procurement\Domain\SupplierAccount\SupplierAccount;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountId;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\SupplierAccountEntity;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Mapper\SupplierAccountMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineSupplierAccountRepository implements SupplierAccountRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private SupplierAccountMapper $mapper,
    ) {
    }

    public function save(SupplierAccount $account): void
    {
        $entity = $this->mapper->toEntity($account);
        $this->em->persist($entity);
        $this->em->flush();
    }

    public function findById(SupplierAccountId $id): ?SupplierAccount
    {
        $entity = $this->em->find(SupplierAccountEntity::class, Uuid::fromString($id->toString()));

        return null !== $entity ? $this->mapper->toDomain($entity) : null;
    }

    public function findByClinicAndSupplier(ClinicId $clinicId, SupplierId $supplierId): ?SupplierAccount
    {
        $entity = $this->em->getRepository(SupplierAccountEntity::class)->findOneBy([
            'clinicId'   => Uuid::fromString($clinicId->toString()),
            'supplierId' => Uuid::fromString($supplierId->toString()),
        ]);

        return null !== $entity ? $this->mapper->toDomain($entity) : null;
    }
}
