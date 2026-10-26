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
        $existing = $this->em->getRepository(UserEntity::class)->find(Uuid::fromString($user->id()->toString()));

        if ($existing instanceof UserEntity) {
            // A known account (activation, password change): the version column guards concurrent writes.
            $this->mapper->applyTo($existing, $user);
        } else {
            $this->em->persist($this->mapper->toEntity($user));
        }

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

    public function recordLogin(UserId $id, \DateTimeImmutable $at): void
    {
        $this->em->createQueryBuilder()
            ->update(UserEntity::class, 'u')
            ->set('u.lastLoginAt', ':at')
            ->where('u.id = :id')
            ->setParameter('at', $at, 'datetime_immutable')
            ->setParameter('id', Uuid::fromString($id->toString()), 'uuid')
            ->getQuery()
            ->execute()
        ;
    }
}
