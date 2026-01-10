<?php

declare(strict_types=1);

namespace App\Tests\Unit\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository;

use App\IdentityAccess\Domain\User;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Domain\ValueObject\UserType;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\BackofficeUserEntity;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\ClinicUserEntity;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\PortalUserEntity;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Factory\DoctrineUserFactory;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Mapper\UserMapper;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Repository\DoctrineUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class DoctrineUserRepositoryTest extends TestCase
{
    public function testSavePersistsAndFlushes(): void
    {
        $user   = $this->user(UserType::CLINIC);
        $mapper = new UserMapper(new DoctrineUserFactory());

        $em = $this->createMock(EntityManagerInterface::class);

        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(UserEntity::class));
        $em->expects(self::once())->method('flush');

        $repo = new DoctrineUserRepository($em, $mapper);
        $repo->save($user);
    }

    public function testFindByIdReturnsDomainUser(): void
    {
        $entity = $this->entity(UserType::CLINIC);
        $user   = $this->user(UserType::CLINIC);
        $mapper = new UserMapper(new DoctrineUserFactory());

        $em = $this->createMock(EntityManagerInterface::class);

        $repoObj = $this->createMock(EntityRepository::class);
        $repoObj->expects(self::once())
            ->method('find')
            ->with(Uuid::fromString('11111111-1111-1111-1111-111111111111'))
            ->willReturn($entity)
        ;

        $em->expects(self::once())
            ->method('getRepository')
            ->with(UserEntity::class)
            ->willReturn($repoObj)
        ;

        $repo = new DoctrineUserRepository($em, $mapper);

        $found = $repo->findById(UserId::fromString('11111111-1111-1111-1111-111111111111'));

        self::assertNotNull($found);
        self::assertSame($user->id()->toString(), $found->id()->toString());
        self::assertSame($user->email(), $found->email());
    }

    public function testFindByIdReturnsNull(): void
    {
        $repoObj = $this->createMock(EntityRepository::class);
        $repoObj->expects(self::once())->method('find')->willReturn(null);

        $em     = $this->createMock(EntityManagerInterface::class);
        $mapper = new UserMapper(new DoctrineUserFactory());

        $em->expects(self::once())
            ->method('getRepository')
            ->with(UserEntity::class)
            ->willReturn($repoObj)
        ;

        $repo = new DoctrineUserRepository($em, $mapper);

        self::assertNull($repo->findById(UserId::fromString('22222222-2222-2222-2222-222222222222')));
    }

    public function testFindByEmailReturnsDomainUser(): void
    {
        $entity = $this->entity(UserType::PORTAL);
        $user   = $this->user(UserType::PORTAL);

        $em     = $this->createMock(EntityManagerInterface::class);
        $mapper = new UserMapper(new DoctrineUserFactory());

        $repoObj = $this->createMock(EntityRepository::class);
        $repoObj->expects(self::once())
            ->method('findOneBy')
            ->with(['email' => 'user@example.com'])
            ->willReturn($entity)
        ;

        $em->expects(self::once())
            ->method('getRepository')
            ->with(UserEntity::class)
            ->willReturn($repoObj)
        ;

        $repo = new DoctrineUserRepository($em, $mapper);

        $found = $repo->findByEmail('user@example.com');

        self::assertNotNull($found);
        self::assertSame($user->id()->toString(), $found->id()->toString());
        self::assertSame($user->email(), $found->email());
    }

    public function testFindByEmailReturnsNull(): void
    {
        $repoObj = $this->createMock(EntityRepository::class);
        $repoObj->expects(self::once())
            ->method('findOneBy')
            ->with(['email' => 'missing@example.com'])
            ->willReturn(null)
        ;

        $em     = $this->createMock(EntityManagerInterface::class);
        $mapper = new UserMapper(new DoctrineUserFactory());

        $em->expects(self::once())
            ->method('getRepository')
            ->with(UserEntity::class)
            ->willReturn($repoObj)
        ;

        $repo = new DoctrineUserRepository($em, $mapper);

        self::assertNull($repo->findByEmail('missing@example.com'));
    }

    #[DataProvider('provideFindByEmailAndTypeReturnsDomainUserCases')]
    public function testFindByEmailAndTypeReturnsDomainUser(UserType $type, string $entityClass): void
    {
        $entity = $this->entity($type);
        $user   = $this->user($type);

        $em     = $this->createMock(EntityManagerInterface::class);
        $mapper = new UserMapper(new DoctrineUserFactory());

        $repoObj = $this->createMock(EntityRepository::class);
        $repoObj->expects(self::once())
            ->method('findOneBy')
            ->with(['email' => 'user@example.com'])
            ->willReturn($entity)
        ;

        $em->expects(self::once())
            ->method('getRepository')
            ->with($entityClass)
            ->willReturn($repoObj)
        ;

        $repo = new DoctrineUserRepository($em, $mapper);

        $found = $repo->findByEmailAndType('user@example.com', $type);

        self::assertNotNull($found);
        self::assertSame($user->id()->toString(), $found->id()->toString());
        self::assertSame($user->email(), $found->email());
    }

    public function testFindByEmailAndTypeReturnsNull(): void
    {
        $repoObj = $this->createMock(EntityRepository::class);
        $repoObj->expects(self::once())
            ->method('findOneBy')
            ->with(['email' => 'missing@example.com'])
            ->willReturn(null)
        ;

        $em     = $this->createMock(EntityManagerInterface::class);
        $mapper = new UserMapper(new DoctrineUserFactory());

        $em->expects(self::once())
            ->method('getRepository')
            ->with(ClinicUserEntity::class)
            ->willReturn($repoObj)
        ;

        $repo = new DoctrineUserRepository($em, $mapper);

        self::assertNull($repo->findByEmailAndType('missing@example.com', UserType::CLINIC));
    }

    /**
     * @return iterable<string, array{UserType, class-string<UserEntity>}>
     */
    public static function provideFindByEmailAndTypeReturnsDomainUserCases(): iterable
    {
        return [
            'clinic'     => [UserType::CLINIC, ClinicUserEntity::class],
            'portal'     => [UserType::PORTAL, PortalUserEntity::class],
            'backoffice' => [UserType::BACKOFFICE, BackofficeUserEntity::class],
        ];
    }

    private function user(UserType $type): User
    {
        return User::register(
            UserId::fromString('11111111-1111-1111-1111-111111111111'),
            'user@example.com',
            '$hash',
            new \DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            $type,
        );
    }

    private function entity(UserType $type): UserEntity
    {
        $entityClass = match ($type) {
            UserType::CLINIC     => ClinicUserEntity::class,
            UserType::PORTAL     => PortalUserEntity::class,
            UserType::BACKOFFICE => BackofficeUserEntity::class,
        };

        $entity = new $entityClass();
        $entity->setId(Uuid::fromString('11111111-1111-1111-1111-111111111111'));
        $entity->setEmail('user@example.com');
        $entity->setPasswordHash('$hash');
        $entity->setCreatedAt(new \DateTimeImmutable('2025-01-01T10:00:00+00:00'));
        $entity->setStatus(\App\IdentityAccess\Domain\ValueObject\UserStatus::ACTIVE);
        $entity->setEmailVerifiedAt(null);

        return $entity;
    }
}
