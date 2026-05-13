<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Money\Domain\ValueObject;

use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\System\Money\Domain\ValueObject\ExchangeRateId;
use PHPUnit\Framework\TestCase;

final class ExchangeRateIdTest extends TestCase
{
    public function testFromStringReturnsId(): void
    {
        $id = ExchangeRateId::fromString('550e8400-e29b-41d4-a716-446655440000');

        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $id->toString());
    }

    public function testGenerateUsesUuidGenerator(): void
    {
        $generator = $this->createStub(UuidGeneratorInterface::class);
        $generator->method('generate')->willReturn('abc');

        $id = ExchangeRateId::generate($generator);

        self::assertSame('abc', $id->toString());
    }

    public function testEquals(): void
    {
        $a = ExchangeRateId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $b = ExchangeRateId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $c = ExchangeRateId::fromString('ffffffff-ffff-ffff-ffff-ffffffffffff');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
