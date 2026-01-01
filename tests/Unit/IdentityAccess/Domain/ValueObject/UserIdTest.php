<?php

declare(strict_types=1);

namespace App\Tests\Unit\IdentityAccess\Domain\ValueObject;

use App\IdentityAccess\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class UserIdTest extends TestCase
{
    public function testFromStringReturnsSameValue(): void
    {
        $id = UserId::fromString('11111111-1111-1111-1111-111111111111');

        self::assertSame('11111111-1111-1111-1111-111111111111', $id->toString());
        self::assertSame('11111111-1111-1111-1111-111111111111', (string) $id);
    }

    public function testEqualsOnSameValue(): void
    {
        $idA = UserId::fromString('11111111-1111-1111-1111-111111111111');
        $idB = UserId::fromString('11111111-1111-1111-1111-111111111111');

        self::assertTrue($idA->equals($idB));
    }
}
