<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Domain\ValueObject\PhoneNumber;
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
        return $this->insertUnlessDuplicate($user);
    }

    /**
     * Same SQL insert: the (type, phone) and (type, email) unique keys turn a
     * duplicate into a no-op. The connection does not report found rows, so
     * the count is 1 on insert and 0 on a duplicate.
     */
    public function addPortalIfPhoneFree(User $user): bool
    {
        return $this->insertUnlessDuplicate($user);
    }

    /**
     * An upsert on the lock table: the row lock it takes lasts until the
     * outer transaction commits or rolls back (unlike a named lock, released
     * before the commit).
     */
    public function lockPortalPhone(PhoneNumber $phone): void
    {
        $this->em->getConnection()->executeStatement(
            'INSERT INTO identity_access__portal_phone_locks (phone) VALUES (:phone) ON DUPLICATE KEY UPDATE phone = phone',
            ['phone' => $phone->toString()],
        );
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

    public function findByEmailAndTypeForUpdate(string $email, UserType $type): ?User
    {
        return $this->findLocked('u.email = :key', $email, Types::STRING, $type);
    }

    public function findByEmailAndType(string $email, UserType $type): ?User
    {
        return $this->findOneOfType($type, ['email' => $email]);
    }

    public function findByPhoneAndType(PhoneNumber $phone, UserType $type): ?User
    {
        return $this->findOneOfType($type, ['phone' => $phone->toString()]);
    }

    public function findByPhoneAndTypeForUpdate(PhoneNumber $phone, UserType $type): ?User
    {
        return $this->findLocked('u.phone = :key', $phone->toString(), Types::STRING, $type);
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

    private function findLocked(string $condition, mixed $key, string $type, ?UserType $userType = null): ?User
    {
        $entity = $this->em->createQueryBuilder()
            ->select('u')
            ->from(null === $userType ? UserEntity::class : self::entityClass($userType), 'u')
            ->where($condition)
            ->setParameter('key', $key, $type)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult()
        ;

        return $entity instanceof UserEntity ? $this->mapper->toDomain($entity) : null;
    }

    /**
     * @param array<string, string> $criteria
     */
    private function findOneOfType(UserType $type, array $criteria): ?User
    {
        $entity = $this->em->getRepository(self::entityClass($type))->findOneBy($criteria);

        return $entity instanceof UserEntity ? $this->mapper->toDomain($entity) : null;
    }

    /**
     * @return class-string<UserEntity>
     */
    private static function entityClass(UserType $type): string
    {
        return match ($type) {
            UserType::CLINIC     => ClinicUserEntity::class,
            UserType::PORTAL     => PortalUserEntity::class,
            UserType::BACKOFFICE => BackofficeUserEntity::class,
        };
    }

    private function insertUnlessDuplicate(User $user): bool
    {
        $inserted = $this->em->getConnection()->executeStatement(
            'INSERT INTO identity_access__users (id, email, phone, password_hash, created_at, status, email_verified_at, phone_verified_at, type, version)'
            . ' VALUES (:id, :email, :phone, :hash, :created, :status, :verified, :phoneVerified, :type, 1)'
            . ' ON DUPLICATE KEY UPDATE id = id',
            [
                'id'            => Uuid::fromString($user->id()->toString())->toBinary(),
                'email'         => $user->email(),
                'phone'         => $user->phone()?->toString(),
                'hash'          => $user->passwordHash(),
                'created'       => $user->createdAt(),
                'status'        => $user->status()->value,
                'verified'      => $user->emailVerifiedAt(),
                'phoneVerified' => $user->phoneVerifiedAt(),
                'type'          => $user->type()->value,
            ],
            ['created' => Types::DATETIME_IMMUTABLE, 'verified' => Types::DATETIME_IMMUTABLE, 'phoneVerified' => Types::DATETIME_IMMUTABLE],
        );

        return 1 === (int) $inserted;
    }
}
