<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Money\Application\Query;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Application\Query\GetExchangeRate\GetExchangeRate;
use App\System\Money\Application\Query\GetExchangeRate\GetExchangeRateHandler;
use App\System\Money\Domain\Exception\ExchangeRateNotFoundException;
use App\System\Money\Domain\ExchangeRate;
use App\System\Money\Domain\Repository\ExchangeRateRepository;
use App\System\Money\Domain\ValueObject\ExchangeRateId;
use App\System\Money\Domain\ValueObject\ExchangeRatePair;
use PHPUnit\Framework\TestCase;

final class GetExchangeRateHandlerTest extends TestCase
{
    private \DateTimeImmutable $now;
    private ExchangeRatePair $pair;

    protected function setUp(): void
    {
        $this->now  = new \DateTimeImmutable('2026-05-14T10:00:00+00:00');
        $this->pair = ExchangeRatePair::of(
            CurrencyCode::fromString('EUR'),
            CurrencyCode::fromString('CHF'),
        );
    }

    public function testReturnsExchangeRateWhenFound(): void
    {
        $mockRate = ExchangeRate::reconstitute(
            id: ExchangeRateId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            pair: $this->pair,
            rate: '0.9523',
            effectiveDate: new \DateTimeImmutable('2026-05-14'),
            source: 'ECB',
            createdAt: $this->now,
        );

        $repository = $this->createStub(ExchangeRateRepository::class);
        $repository->method('findRateAt')->willReturn($mockRate);

        $handler = new GetExchangeRateHandler($repository);
        $result  = $handler(new GetExchangeRate($this->pair, new \DateTimeImmutable('2026-05-14')));

        self::assertSame('EUR/CHF', $result->pair()->toString());
        self::assertSame('0.9523', $result->rate());
    }

    public function testThrowsWhenNotFound(): void
    {
        $repository = $this->createStub(ExchangeRateRepository::class);
        $repository->method('findRateAt')->willReturn(null);

        $handler = new GetExchangeRateHandler($repository);

        $this->expectException(ExchangeRateNotFoundException::class);

        $handler(new GetExchangeRate($this->pair, new \DateTimeImmutable('2026-05-14')));
    }
}
