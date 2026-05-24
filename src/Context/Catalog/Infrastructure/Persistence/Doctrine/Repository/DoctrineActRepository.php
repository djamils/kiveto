<?php

declare(strict_types=1);

namespace App\Context\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Catalog\Domain\Act\Act;
use App\Context\Catalog\Domain\Act\Repository\ActRepositoryInterface;
use App\Context\Catalog\Domain\Act\ValueObject\ActCode;
use App\Context\Catalog\Domain\Act\ValueObject\ActId;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\ActEntity;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Mapper\ActMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineActRepository implements ActRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private ActMapper $mapper,
    ) {
    }

    public function save(Act $act): void
    {
        $uuid     = Uuid::fromString($act->id()->toString());
        $existing = $this->em->find(ActEntity::class, $uuid);

        if (null === $existing) {
            $entity = $this->mapper->toEntity($act, null);
            $this->em->persist($entity);
        } else {
            $this->mapper->toEntity($act, $existing);
        }

        $this->em->flush();
    }

    public function findById(ActId $id, ClinicId $clinicId): ?Act
    {
        $entity = $this->em->getRepository(ActEntity::class)->findOneBy([
            'id'       => Uuid::fromString($id->toString()),
            'tenantId' => Uuid::fromString($clinicId->toString()),
        ]);

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }

    public function findByCode(ActCode $code, ClinicId $clinicId): ?Act
    {
        $entity = $this->em->getRepository(ActEntity::class)->findOneBy([
            'code'     => $code->toString(),
            'tenantId' => Uuid::fromString($clinicId->toString()),
        ]);

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }

    public function existsByCode(ActCode $code, ClinicId $clinicId): bool
    {
        $count = $this->em->getRepository(ActEntity::class)->count([
            'code'     => $code->toString(),
            'tenantId' => Uuid::fromString($clinicId->toString()),
        ]);

        return $count > 0;
    }
}
