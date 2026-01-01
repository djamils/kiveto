<?php

declare(strict_types=1);

namespace App\Tests\Unit\IdentityAccess\Infrastructure\Persistence\Doctrine\Mapper;

use App\IdentityAccess\Domain\User;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Domain\ValueObject\UserStatus;
use App\IdentityAccess\Domain\ValueObject\UserType;
use App\IdentityAccess\Infrastructure\Persistence\Doctrine\Factory\DoctrineUserFactory;
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
            UserType::PORTAL,
        );

        $mapper    = new UserMapper(new DoctrineUserFactory());
        $entity    = $mapper->toEntity($domain);
        $roundTrip = $mapper->toDomain($entity);

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
