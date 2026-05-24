<?php

declare(strict_types=1);

namespace App\Context\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Catalog\Domain\Package\Package;
use App\Context\Catalog\Domain\Package\Repository\PackageRepositoryInterface;
use App\Context\Catalog\Domain\Package\ValueObject\PackageCode;
use App\Context\Catalog\Domain\Package\ValueObject\PackageId;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\PackageComponentEntity;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\PackageEntity;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Mapper\PackageMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrinePackageRepository implements PackageRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private PackageMapper $mapper,
    ) {
    }

    public function save(Package $package): void
    {
        $uuid     = Uuid::fromString($package->id()->toString());
        $existing = $this->em->find(PackageEntity::class, $uuid);

        /** @var list<PackageComponentEntity> $componentEntities */
        $componentEntities = $this->em->getRepository(PackageComponentEntity::class)->findBy([
            'packageId' => $uuid,
        ]);

        if (null === $existing) {
            $entity = $this->mapper->toEntity($package, $componentEntities, null);
            $this->em->persist($entity);
        } else {
            $this->mapper->toEntity($package, $componentEntities, $existing);
        }

        // Sync components: remove all and re-insert
        foreach ($componentEntities as $componentEntity) {
            $this->em->remove($componentEntity);
        }

        foreach ($package->components() as $component) {
            $componentEntity = new PackageComponentEntity();
            $componentEntity->setId(Uuid::fromString($component->id()->toString()));
            $componentEntity->setPackageId($uuid);
            $componentEntity->setItemType($component->itemRef()->type()->value);
            $componentEntity->setItemId($component->itemRef()->id());
            $componentEntity->setQuantity($component->quantity()->value());
            $componentEntity->setWeight($component->weight());
            $this->em->persist($componentEntity);
        }

        $this->em->flush();
    }

    public function findById(PackageId $id, ClinicId $clinicId): ?Package
    {
        $uuid   = Uuid::fromString($id->toString());
        $entity = $this->em->getRepository(PackageEntity::class)->findOneBy([
            'id'       => $uuid,
            'tenantId' => Uuid::fromString($clinicId->toString()),
        ]);

        if (null === $entity) {
            return null;
        }

        return $this->hydrate($entity);
    }

    public function findByCode(PackageCode $code, ClinicId $clinicId): ?Package
    {
        $entity = $this->em->getRepository(PackageEntity::class)->findOneBy([
            'code'     => $code->toString(),
            'tenantId' => Uuid::fromString($clinicId->toString()),
        ]);

        if (null === $entity) {
            return null;
        }

        return $this->hydrate($entity);
    }

    public function existsByCode(PackageCode $code, ClinicId $clinicId): bool
    {
        $count = $this->em->getRepository(PackageEntity::class)->count([
            'code'     => $code->toString(),
            'tenantId' => Uuid::fromString($clinicId->toString()),
        ]);

        return $count > 0;
    }

    private function hydrate(PackageEntity $entity): Package
    {
        /** @var list<PackageComponentEntity> $componentEntities */
        $componentEntities = $this->em->getRepository(PackageComponentEntity::class)->findBy([
            'packageId' => $entity->getId(),
        ]);

        return $this->mapper->toDomain($entity, $componentEntities);
    }
}
