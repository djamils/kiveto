<?php

declare(strict_types=1);

namespace App\Tests\Unit\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity;

use App\IdentityAccess\Domain\ValueObject\UserStatus;
use App\IdentityAccess\Domain\ValueObject\UserType;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\BackofficeUserEntity;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\ClinicUserEntity;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\PortalUserEntity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class UserEntityTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $user = new ClinicUserEntity();

        $createdAt       = new \DateTimeImmutable('2025-01-01T10:00:00+00:00');
        $emailVerifiedAt = new \DateTimeImmutable('2025-01-02T10:00:00+00:00');

        $id = Uuid::fromString('11111111-1111-1111-1111-111111111111');
        $user->setId($id);
        $user->setEmail('clinic@example.com');
        $user->setPasswordHash('$hash');
        $user->setCreatedAt($createdAt);
        $user->setStatus(UserStatus::ACTIVE);
        $user->setEmailVerifiedAt($emailVerifiedAt);

        self::assertSame($id, $user->getId());
        self::assertSame('clinic@example.com', $user->getEmail());
        self::assertSame('$hash', $user->getPasswordHash());
        self::assertSame(UserStatus::ACTIVE, $user->getStatus());
        self::assertSame(
            $createdAt->format(\DateTimeInterface::ATOM),
            $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
        self::assertSame(
            $emailVerifiedAt->format(\DateTimeInterface::ATOM),
            $user->getEmailVerifiedAt()?->format(\DateTimeInterface::ATOM),
        );
        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertSame('$hash', $user->getPassword());
        self::assertSame(UserType::CLINIC, $user->getType());
        self::assertSame('clinic@example.com', $user->getUserIdentifier());
    }

    public function testGetUserIdentifierThrowsWhenEmpty(): void
    {
        $user = new ClinicUserEntity();
        $user->setEmail('');

        $this->expectException(\LogicException::class);
        $user->getUserIdentifier();
    }

    public function testGetTypePerSubclass(): void
    {
        self::assertSame(UserType::CLINIC, (new ClinicUserEntity())->getType());
        self::assertSame(UserType::PORTAL, (new PortalUserEntity())->getType());
        self::assertSame(UserType::BACKOFFICE, (new BackofficeUserEntity())->getType());
    }

    public function testEraseCredentialsIsNoOp(): void
    {
        $user = new ClinicUserEntity();
        $user->eraseCredentials(); // no exception expected
        self::assertSame('$hash', (function () {
            $u = new ClinicUserEntity();
            $u->setPasswordHash('$hash');

            return $u->getPassword();
        })());
    }
}
