<?php

declare(strict_types=1);

namespace App\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\IdentityAccess\Domain\User;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Domain\ValueObject\UserType;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\BackofficeUser;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\ClinicUser;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\PortalUser;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User as UserEntity;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Mapper\UserMapper;
use Doctrine\ORM\EntityManagerInterface;

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
        $entity = $this->em->getRepository(UserEntity::class)->find($id->toString());

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
            UserType::CLINIC     => ClinicUser::class,
            UserType::PORTAL     => PortalUser::class,
            UserType::BACKOFFICE => BackofficeUser::class,
        };

        /** @var UserEntity|null $entity */
        $entity = $this->em->getRepository($entityClass)->findOneBy(['email' => $email]);

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }
}
