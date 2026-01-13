<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Domain\ValueObject;

use App\AccessControl\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class UserIdTest extends TestCase
{
    public function testFromString(): void
    {
        $uuid   = '22222222-2222-2222-2222-222222222222';
        $userId = UserId::fromString($uuid);

        self::assertSame($uuid, $userId->toString());
    }

    public function testEquals(): void
    {
        $userId1 = UserId::fromString('22222222-2222-2222-2222-222222222222');
        $userId2 = UserId::fromString('22222222-2222-2222-2222-222222222222');
        $userId3 = UserId::fromString('33333333-3333-3333-3333-333333333333');

        self::assertTrue($userId1->equals($userId2));
        self::assertFalse($userId1->equals($userId3));
    }

    public function testThrowsExceptionWhenEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Identifier cannot be empty.');

        UserId::fromString('');
    }
}
