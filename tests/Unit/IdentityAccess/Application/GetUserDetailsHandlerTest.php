<?php

declare(strict_types=1);

namespace App\Tests\Unit\IdentityAccess\Application;

use App\IdentityAccess\Application\Query\GetUserDetails\GetUserDetails;
use App\IdentityAccess\Application\Query\GetUserDetails\GetUserDetailsHandler;
use App\IdentityAccess\Domain\User;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Infrastructure\Repository\InMemoryUserRepository;
use PHPUnit\Framework\TestCase;

final class GetUserDetailsHandlerTest extends TestCase
{
    public function testReturnsDtoWhenUserExists(): void
    {
        $repo      = new InMemoryUserRepository();
        $userId    = UserId::fromString('11111111-1111-1111-1111-111111111111');
        $createdAt = new \DateTimeImmutable('2025-01-01T10:00:00+00:00');
        $user      = User::register(
            $userId,
            'user@example.com',
            '$hash',
            $createdAt,
            \App\IdentityAccess\Domain\ValueObject\UserType::CLINIC,
        );
        $repo->save($user);

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
        $repo    = new InMemoryUserRepository();
        $handler = new GetUserDetailsHandler($repo);

        $dto = $handler(new GetUserDetails('non-existing'));

        self::assertNull($dto);
    }
}
