<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Domain\ValueObject;

use App\Context\Billing\Domain\Exception\InvalidDiscountException;
use App\Context\Billing\Domain\ValueObject\Discount;
use App\Context\Billing\Domain\ValueObject\DiscountKind;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class DiscountTest extends TestCase
{
    public function testAPercentageKeepsItsParameters(): void
    {
        $discount = Discount::percentage('12.5', '  Client fidèle  ');

        self::assertSame(DiscountKind::PERCENTAGE, $discount->kind());
        self::assertSame('12.5', $discount->value());
        self::assertSame('Client fidèle', $discount->reason());
    }

    public function testAFixedDiscountKeepsItsAmount(): void
    {
        $discount = Discount::fixed(Money::fromMinorUnits(1500, CurrencyCode::fromString('EUR')));

        self::assertSame(DiscountKind::FIXED, $discount->kind());
        self::assertSame('1500', $discount->value());
        self::assertNull($discount->reason());
    }

    public function testABlankReasonBecomesNull(): void
    {
        self::assertNull(Discount::percentage('10', '   ')->reason());
    }

    public function testAMalformedPercentageIsRejected(): void
    {
        $this->expectException(InvalidDiscountException::class);
        $this->expectExceptionMessage('Invalid discount percentage "dix"');

        Discount::percentage('dix');
    }

    public function testAPercentageAboveOneHundredIsRejected(): void
    {
        $this->expectException(InvalidDiscountException::class);
        $this->expectExceptionMessage('above 0 and at most 100');

        Discount::percentage('120');
    }

    public function testAZeroPercentageIsRejected(): void
    {
        $this->expectException(InvalidDiscountException::class);

        Discount::percentage('0');
    }

    public function testANonPositiveFixedDiscountIsRejected(): void
    {
        $this->expectException(InvalidDiscountException::class);
        $this->expectExceptionMessage('must be a positive amount');

        Discount::fixed(Money::zero(CurrencyCode::fromString('EUR')));
    }

    public function testItIsReconstitutedFromItsStoredParts(): void
    {
        $percentage = Discount::reconstitute(DiscountKind::PERCENTAGE, '10', 'Fidélité');
        self::assertSame('10', $percentage->value());

        $fixed = Discount::reconstitute(DiscountKind::FIXED, '750', null);
        self::assertSame(DiscountKind::FIXED, $fixed->kind());
        self::assertSame('750', $fixed->value());
    }
}
