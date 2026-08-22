<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Domain\ValueObject;

use App\Context\Billing\Domain\Exception\InvalidInvoiceNumberException;
use App\Context\Billing\Domain\ValueObject\InvoiceNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InvoiceNumberTest extends TestCase
{
    public function testItIsBuiltFromAYearAndASequence(): void
    {
        $number = InvoiceNumber::of(2026, 42);

        self::assertSame('FAC-2026-000042', $number->toString());
        self::assertSame(2026, $number->year());
        self::assertSame(42, $number->sequence());
    }

    public function testItIsReadBackFromItsString(): void
    {
        $number = InvoiceNumber::fromString('FAC-2025-000198');

        self::assertSame(2025, $number->year());
        self::assertSame(198, $number->sequence());
        self::assertTrue($number->equals(InvoiceNumber::of(2025, 198)));
        self::assertFalse($number->equals(InvoiceNumber::of(2025, 199)));
    }

    #[DataProvider('provideAMalformedNumberIsRejectedCases')]
    public function testAMalformedNumberIsRejected(string $value): void
    {
        $this->expectException(InvalidInvoiceNumberException::class);
        $this->expectExceptionMessage('Expected FAC-YYYY-NNNNNN');

        InvoiceNumber::fromString($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAMalformedNumberIsRejectedCases(): iterable
    {
        yield 'empty' => [''];
        yield 'no prefix' => ['2026-000042'];
        yield 'short sequence' => ['FAC-2026-42'];
        yield 'short year' => ['FAC-26-000042'];
        yield 'lowercase' => ['fac-2026-000042'];
        yield 'trailing text' => ['FAC-2026-000042-BIS'];
    }

    public function testAYearOutsideFourDigitsIsRejected(): void
    {
        $this->expectException(InvalidInvoiceNumberException::class);

        InvoiceNumber::of(999, 1);
    }

    public function testASequenceBelowOneIsRejected(): void
    {
        $this->expectException(InvalidInvoiceNumberException::class);

        InvoiceNumber::of(2026, 0);
    }

    public function testASequenceAboveSixDigitsIsRejected(): void
    {
        $this->expectException(InvalidInvoiceNumberException::class);

        InvoiceNumber::of(2026, 1000000);
    }
}
