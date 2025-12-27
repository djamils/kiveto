<?php

declare(strict_types=1);

namespace App\Tests\Unit\IdentityAccess\Infrastructure\Doctrine\Mapper;

use App\IdentityAccess\Domain\User;
use App\IdentityAccess\Domain\UserId;
use App\IdentityAccess\Domain\UserStatus;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Entity\User as DoctrineUser;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Mapper\UserMapper;
use PHPUnit\Framework\TestCase;

final class UserMapperTest extends TestCase
{
    public function testRoundTripDomainToEntityAndBack(): void
    {
        $domain = User::reconstitute(
            UserId::fromString('11111111-1111-1111-1111-111111111111'),
            'user@example.com',
            '$hash',
            new \DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            UserStatus::ACTIVE,
            new \DateTimeImmutable('2025-01-02T10:00:00+00:00'),
            \App\IdentityAccess\Domain\UserType::PORTAL,
        );

        $mapper    = new UserMapper();
        $entity    = $mapper->toEntity($domain);
        $roundTrip = $mapper->toDomain($entity);

        self::assertInstanceOf(DoctrineUser::class, $entity);
        self::assertSame($domain->id()->toString(), $entity->getId());
        self::assertSame($domain->email(), $entity->getEmail());
        self::assertSame($domain->passwordHash(), $entity->getPasswordHash());
        self::assertSame(
            $domain->createdAt()->format(\DateTimeInterface::ATOM),
            $entity->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
        self::assertSame($domain->status()->value, $entity->getStatus()->value);
        self::assertSame(
            $domain->emailVerifiedAt()?->format(\DateTimeInterface::ATOM),
            $entity->getEmailVerifiedAt()?->format(\DateTimeInterface::ATOM),
        );
        self::assertSame($domain->type()->value, $entity->getType()->value);

        self::assertSame($domain->id()->toString(), $roundTrip->id()->toString());
        self::assertSame($domain->email(), $roundTrip->email());
        self::assertSame($domain->passwordHash(), $roundTrip->passwordHash());
        self::assertSame(
            $domain->createdAt()->format(\DateTimeInterface::ATOM),
            $roundTrip->createdAt()->format(\DateTimeInterface::ATOM),
        );
        self::assertSame($domain->status()->value, $roundTrip->status()->value);
        self::assertSame(
            $domain->emailVerifiedAt()?->format(\DateTimeInterface::ATOM),
            $roundTrip->emailVerifiedAt()?->format(\DateTimeInterface::ATOM),
        );
        self::assertSame($domain->type()->value, $roundTrip->type()->value);
    }
}
