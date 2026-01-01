<?php

declare(strict_types=1);

namespace App\Tests\Unit\IdentityAccess\Application\Query\GetUserDetails;

use App\IdentityAccess\Application\Query\GetUserDetails\GetUserDetails;
use App\IdentityAccess\Application\Query\GetUserDetails\GetUserDetailsHandler;
use App\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\IdentityAccess\Domain\User;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Domain\ValueObject\UserType;
use PHPUnit\Framework\TestCase;

final class GetUserDetailsHandlerTest extends TestCase
{
    public function testReturnsDtoWhenUserExists(): void
    {
        $repo      = $this->createStub(UserRepositoryInterface::class);
        $userId    = UserId::fromString('11111111-1111-1111-1111-111111111111');
        $createdAt = new \DateTimeImmutable('2025-01-01T10:00:00+00:00');
        $user      = User::register(
            $userId,
            'user@example.com',
            '$hash',
            $createdAt,
            UserType::CLINIC,
        );
        $repo->method('findById')->willReturn($user);

        $handler = new GetUserDetailsHandler($repo);

        $dto = $handler(new GetUserDetails($userId->toString()));

        self::assertNotNull($dto);
        self::assertSame($userId->toString(), $dto->id);
        self::assertSame('user@example.com', $dto->email);
        self::assertSame($createdAt->format(\DateTimeInterface::ATOM), $dto->createdAt);
        self::assertSame('ACTIVE', $dto->status);
        self::assertNull($dto->emailVerifiedAt);
    }

    public function testReturnsNullWhenUserNotFound(): void
    {
        $repo = $this->createStub(UserRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);
        $handler = new GetUserDetailsHandler($repo);

        $dto = $handler(new GetUserDetails('non-existing'));

        self::assertNull($dto);
    }
}
