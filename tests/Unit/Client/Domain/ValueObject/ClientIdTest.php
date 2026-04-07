<?php

declare(strict_types=1);

namespace App\Tests\Unit\Client\Domain\ValueObject;

use App\Client\Domain\ValueObject\ClientId;
use PHPUnit\Framework\TestCase;

final class ClientIdTest extends TestCase
{
    public function testCreatesFromString(): void
    {
        $uuid     = '01234567-89ab-cdef-0123-456789abcdef';
        $clientId = ClientId::fromString($uuid);

        self::assertSame($uuid, $clientId->toString());
    }

    public function testEquality(): void
    {
        $uuid      = '01234567-89ab-cdef-0123-456789abcdef';
        $clientId1 = ClientId::fromString($uuid);
        $clientId2 = ClientId::fromString($uuid);

        self::assertTrue($clientId1->equals($clientId2));
    }

    public function testInequality(): void
    {
        $uuid1     = '01234567-89ab-cdef-0123-456789abcdef';
        $uuid2     = '12345678-9abc-def0-1234-56789abcdef0';
        $clientId1 = ClientId::fromString($uuid1);
        $clientId2 = ClientId::fromString($uuid2);

        self::assertFalse($clientId1->equals($clientId2));
    }
}
