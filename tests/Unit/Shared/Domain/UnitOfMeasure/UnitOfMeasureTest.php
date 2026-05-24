<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\UnitOfMeasure;

use App\Shared\Domain\UnitOfMeasure\Exception\InvalidUnitOfMeasureException;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use PHPUnit\Framework\TestCase;

final class UnitOfMeasureTest extends TestCase
{
    public function testFromStringAcceptsValidCodes(): void
    {
        self::assertSame('TABLET', UnitOfMeasure::fromString('TABLET')->toString());
        self::assertSame('ML', UnitOfMeasure::fromString('ML')->toString());
        self::assertSame('KG', UnitOfMeasure::fromString('KG')->toString());
        self::assertSame('L', UnitOfMeasure::fromString('L')->toString());
        self::assertSame('UNIT', UnitOfMeasure::fromString('UNIT')->toString());
        self::assertSame('BOX_LARGE', UnitOfMeasure::fromString('BOX_LARGE')->toString());
    }

    public function testFromStringRejectsEmpty(): void
    {
        $this->expectException(InvalidUnitOfMeasureException::class);
        UnitOfMeasure::fromString('');
    }

    public function testFromStringRejectsLowercase(): void
    {
        $this->expectException(InvalidUnitOfMeasureException::class);
        UnitOfMeasure::fromString('tablet');
    }

    public function testFromStringRejectsMixedCase(): void
    {
        $this->expectException(InvalidUnitOfMeasureException::class);
        UnitOfMeasure::fromString('Tablet');
    }

    public function testFromStringRejectsStartingWithUnderscore(): void
    {
        $this->expectException(InvalidUnitOfMeasureException::class);
        UnitOfMeasure::fromString('_TABLET');
    }

    public function testFromStringRejectsCodeTooLong(): void
    {
        // 18 chars — max is 17 (1 + 16)
        $this->expectException(InvalidUnitOfMeasureException::class);
        UnitOfMeasure::fromString('ABCDEFGHIJKLMNOPQR');
    }

    public function testFromStringAcceptsMaxLengthCode(): void
    {
        // 17 chars exactly: 1 leading + 16 more
        $code = UnitOfMeasure::fromString('ABCDEFGHIJKLMNOPQ');
        self::assertSame('ABCDEFGHIJKLMNOPQ', $code->toString());
    }

    public function testFromStringRejectsDigits(): void
    {
        $this->expectException(InvalidUnitOfMeasureException::class);
        UnitOfMeasure::fromString('TABLET1');
    }

    public function testEqualsSameValue(): void
    {
        self::assertTrue(
            UnitOfMeasure::fromString('TABLET')->equals(UnitOfMeasure::fromString('TABLET')),
        );
    }

    public function testEqualsDifferentValue(): void
    {
        self::assertFalse(
            UnitOfMeasure::fromString('TABLET')->equals(UnitOfMeasure::fromString('CAPSULE')),
        );
    }
}
