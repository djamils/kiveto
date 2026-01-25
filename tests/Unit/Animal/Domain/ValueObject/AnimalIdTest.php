<?php

declare(strict_types=1);

namespace App\Tests\Unit\Animal\Domain\ValueObject;

use App\Animal\Domain\ValueObject\AnimalId;
use PHPUnit\Framework\TestCase;

final class AnimalIdTest extends TestCase
{
    public function testFromString(): void
    {
        $uuid = '01234567-89ab-cdef-0123-456789abcdef';
        $id   = AnimalId::fromString($uuid);

        self::assertSame($uuid, $id->toString());
        self::assertSame($uuid, $id->value());
    }

    public function testFromStringCreatesUniqueInstances(): void
    {
        $uuid1 = '01234567-89ab-cdef-0123-456789abcdef';
        $uuid2 = '11234567-89ab-cdef-0123-456789abcdef';

        $id1 = AnimalId::fromString($uuid1);
        $id2 = AnimalId::fromString($uuid2);

        self::assertNotSame($id1->toString(), $id2->toString());
    }

    public function testEquals(): void
    {
        $uuid = '01234567-89ab-cdef-0123-456789abcdef';
        $id1  = AnimalId::fromString($uuid);
        $id2  = AnimalId::fromString($uuid);
        $id3  = AnimalId::fromString('11234567-89ab-cdef-0123-456789abcdef');

        self::assertTrue($id1->equals($id2));
        self::assertFalse($id1->equals($id3));
    }
}
