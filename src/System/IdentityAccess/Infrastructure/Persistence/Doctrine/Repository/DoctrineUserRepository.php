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
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
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

    /**
     * Written with SQL rather than through the unit of work: a duplicate
     * address is a no-op here, where a failed flush would close the entity
     * manager of the whole request.
     */
    public function addIfEmailFree(User $user): bool
    {
        $inserted = $this->em->getConnection()->executeStatement(
            'INSERT INTO identity_access__users (id, email, password_hash, created_at, status, email_verified_at, type, version)'
            . ' VALUES (:id, :email, :hash, :created, :status, :verified, :type, 1)'
            . ' ON DUPLICATE KEY UPDATE id = id',
            [
                'id'       => Uuid::fromString($user->id()->toString())->toBinary(),
                'email'    => $user->email(),
                'hash'     => $user->passwordHash(),
                'created'  => $user->createdAt(),
                'status'   => $user->status()->value,
                'verified' => $user->emailVerifiedAt(),
                'type'     => $user->type()->value,
            ],
            ['created' => Types::DATETIME_IMMUTABLE, 'verified' => Types::DATETIME_IMMUTABLE],
        );

        return 1 === (int) $inserted;
    }

    public function findById(UserId $id): ?User
    {
        $entity = $this->em->getRepository(UserEntity::class)->find(Uuid::fromString($id->toString()));

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }

    public function findByIdForUpdate(UserId $id): ?User
    {
        return $this->findLocked('u.id = :key', Uuid::fromString($id->toString()), 'uuid');
    }

    public function findByEmailForUpdate(string $email): ?User
    {
        return $this->findLocked('u.email = :key', $email, Types::STRING);
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

    private function findLocked(string $condition, mixed $key, string $type): ?User
    {
        $entity = $this->em->createQueryBuilder()
            ->select('u')
            ->from(UserEntity::class, 'u')
            ->where($condition)
            ->setParameter('key', $key, $type)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult()
        ;

        return $entity instanceof UserEntity ? $this->mapper->toDomain($entity) : null;
    }
}
