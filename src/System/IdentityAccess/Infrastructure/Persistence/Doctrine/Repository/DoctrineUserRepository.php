<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\BackofficeUserEntity;
use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\ClinicUserEntity;
use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\PortalUserEntity;
use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Mapper\UserMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserMapper $mapper,
    ) {
    }

    public function save(User $user): void
    {
        $entity = $this->mapper->toEntity($user);
        $this->em->persist($entity);
        $this->em->flush();
    }

    public function findById(UserId $id): ?User
    {
        $entity = $this->em->getRepository(UserEntity::class)->find(Uuid::fromString($id->toString()));

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }

    public function findByEmail(string $email): ?User
    {
        $entity = $this->em->getRepository(UserEntity::class)->findOneBy(['email' => $email]);

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }

    public function findByEmailAndType(string $email, UserType $type): ?User
    {
        $entityClass = match ($type) {
            UserType::CLINIC     => ClinicUserEntity::class,
            UserType::PORTAL     => PortalUserEntity::class,
            UserType::BACKOFFICE => BackofficeUserEntity::class,
        };

        /** @var UserEntity|null $entity */
        $entity = $this->em->getRepository($entityClass)->findOneBy(['email' => $email]);

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }
}
