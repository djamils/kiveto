<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Taxation\Domain\ValueObject;

use App\System\Taxation\Domain\ValueObject\ValidityPeriod;
use PHPUnit\Framework\TestCase;

final class ValidityPeriodTest extends TestCase
{
    public function testOpenEndedContainsPast(): void
    {
        $period = ValidityPeriod::openEndedFrom(new \DateTimeImmutable('2020-01-01'));

        self::assertTrue($period->contains(new \DateTimeImmutable('2021-06-01')));
    }

    public function testOpenEndedContainsPresent(): void
    {
        $period = ValidityPeriod::openEndedFrom(new \DateTimeImmutable('2020-01-01'));

        self::assertTrue($period->contains(new \DateTimeImmutable('2020-01-01')));
    }

    public function testOpenEndedDoesNotContainBefore(): void
    {
        $period = ValidityPeriod::openEndedFrom(new \DateTimeImmutable('2020-01-01'));

        self::assertFalse($period->contains(new \DateTimeImmutable('2019-12-31')));
    }

    public function testClosedContainsMidRange(): void
    {
        $period = ValidityPeriod::between(
            new \DateTimeImmutable('2020-01-01'),
            new \DateTimeImmutable('2022-12-31'),
        );

        self::assertTrue($period->contains(new \DateTimeImmutable('2021-06-01')));
    }

    public function testClosedDoesNotContainBefore(): void
    {
        $period = ValidityPeriod::between(
            new \DateTimeImmutable('2020-01-01'),
            new \DateTimeImmutable('2022-12-31'),
        );

        self::assertFalse($period->contains(new \DateTimeImmutable('2019-12-31')));
    }

    public function testClosedDoesNotContainAfter(): void
    {
        $period = ValidityPeriod::between(
            new \DateTimeImmutable('2020-01-01'),
            new \DateTimeImmutable('2022-12-31'),
        );

        self::assertFalse($period->contains(new \DateTimeImmutable('2023-01-01')));
    }

    public function testBetweenWithFromGreaterThanToThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ValidityPeriod::between(
            new \DateTimeImmutable('2022-01-01'),
            new \DateTimeImmutable('2020-01-01'),
        );
    }
}
