<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Money\Domain;

use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\Event\ExchangeRateImported;
use App\System\Money\Domain\ExchangeRate;
use App\System\Money\Domain\ValueObject\ExchangeRateId;
use App\System\Money\Domain\ValueObject\ExchangeRatePair;
use PHPUnit\Framework\TestCase;

final class ExchangeRateTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-05-14T10:00:00+00:00');
    }

    public function testCreateRecordsExchangeRateImportedEvent(): void
    {
        $id   = ExchangeRateId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $pair = ExchangeRatePair::of(
            CurrencyCode::fromString('EUR'),
            CurrencyCode::fromString('CHF'),
        );

        $exchangeRate = ExchangeRate::create(
            id: $id,
            pair: $pair,
            rate: '0.9523',
            effectiveDate: new \DateTimeImmutable('2026-05-14'),
            source: 'ECB',
            createdAt: $this->now,
        );

        $events = $exchangeRate->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ExchangeRateImported::class, $events[0]);
    }

    public function testReconstituteRecordsNoEvents(): void
    {
        $id   = ExchangeRateId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $pair = ExchangeRatePair::of(
            CurrencyCode::fromString('EUR'),
            CurrencyCode::fromString('CHF'),
        );

        $exchangeRate = ExchangeRate::reconstitute(
            id: $id,
            pair: $pair,
            rate: '0.9523',
            effectiveDate: new \DateTimeImmutable('2026-05-14'),
            source: 'ECB',
            createdAt: $this->now,
        );

        self::assertCount(0, $exchangeRate->pullDomainEvents());
    }

    public function testCreateThrowsOnNonPositiveRateZero(): void
    {
        $id   = ExchangeRateId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $pair = ExchangeRatePair::of(
            CurrencyCode::fromString('EUR'),
            CurrencyCode::fromString('CHF'),
        );

        $this->expectException(\InvalidArgumentException::class);

        ExchangeRate::create(
            id: $id,
            pair: $pair,
            rate: '0',
            effectiveDate: new \DateTimeImmutable('2026-05-14'),
            source: 'ECB',
            createdAt: $this->now,
        );
    }

    public function testCreateThrowsOnNonPositiveRateNegative(): void
    {
        $id   = ExchangeRateId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $pair = ExchangeRatePair::of(
            CurrencyCode::fromString('EUR'),
            CurrencyCode::fromString('CHF'),
        );

        $this->expectException(\InvalidArgumentException::class);

        ExchangeRate::create(
            id: $id,
            pair: $pair,
            rate: '-1.0',
            effectiveDate: new \DateTimeImmutable('2026-05-14'),
            source: 'ECB',
            createdAt: $this->now,
        );
    }
}
