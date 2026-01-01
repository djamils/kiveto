<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Identifier;

use App\Tests\Unit\Shared\Domain\Identifier\Fixture\AnotherUuidId;
use App\Tests\Unit\Shared\Domain\Identifier\Fixture\TestUuidId;
use PHPUnit\Framework\TestCase;

final class AbstractUuidIdTest extends TestCase
{
    public function testValueIsTrimmedAndExposed(): void
    {
        $id = new TestUuidId('  uuid-123  ');

        self::assertSame('uuid-123', $id->toString());
        self::assertSame('uuid-123', (string) $id);
    }

    public function testEmptyValueThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Identifier cannot be empty.');

        new TestUuidId('   ');
    }

    public function testEqualsReturnsTrueForSameClassAndValue(): void
    {
        $idA = new TestUuidId('uuid-abc');
        $idB = new TestUuidId('uuid-abc');

        self::assertTrue($idA->equals($idB));
    }

    public function testEqualsReturnsFalseForDifferentValue(): void
    {
        $idA = new TestUuidId('uuid-abc');
        $idB = new TestUuidId('uuid-def');

        self::assertFalse($idA->equals($idB));
    }

    public function testEqualsReturnsFalseForDifferentSubclass(): void
    {
        $idA = new TestUuidId('uuid-abc');
        $idB = new AnotherUuidId('uuid-abc');

        self::assertFalse($idA->equals($idB));
    }
}
