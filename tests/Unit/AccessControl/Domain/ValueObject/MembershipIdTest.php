<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Domain\ValueObject;

use App\AccessControl\Domain\ValueObject\MembershipId;
use PHPUnit\Framework\TestCase;

final class MembershipIdTest extends TestCase
{
    public function testFromStringCreatesValidId(): void
    {
        $uuid = '01234567-89ab-cdef-0123-456789abcdef';
        $id   = MembershipId::fromString($uuid);

        self::assertInstanceOf(MembershipId::class, $id);
        self::assertSame($uuid, $id->toString());
    }

    public function testToStringReturnsOriginalValue(): void
    {
        $uuid = '11111111-2222-3333-4444-555555555555';
        $id   = MembershipId::fromString($uuid);

        self::assertSame($uuid, $id->toString());
    }

    public function testEqualsReturnsTrueForSameId(): void
    {
        $uuid = '01234567-89ab-cdef-0123-456789abcdef';
        $id1  = MembershipId::fromString($uuid);
        $id2  = MembershipId::fromString($uuid);

        self::assertTrue($id1->equals($id2));
    }

    public function testEqualsReturnsFalseForDifferentId(): void
    {
        $id1 = MembershipId::fromString('11111111-1111-1111-1111-111111111111');
        $id2 = MembershipId::fromString('22222222-2222-2222-2222-222222222222');

        self::assertFalse($id1->equals($id2));
    }
}
