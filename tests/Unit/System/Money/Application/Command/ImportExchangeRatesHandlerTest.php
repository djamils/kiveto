<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Money\Application\Command;

use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Application\Command\ImportExchangeRates\ImportExchangeRates;
use App\System\Money\Application\Command\ImportExchangeRates\ImportExchangeRatesHandler;
use App\System\Money\Application\Port\ExchangeRateProvider;
use App\System\Money\Domain\Repository\ExchangeRateRepository;
use App\System\Money\Domain\ValueObject\ExchangeRatePair;
use App\System\Money\Domain\ValueObject\HistoricalRate;
use PHPUnit\Framework\TestCase;

final class ImportExchangeRatesHandlerTest extends TestCase
{
    public function testImportsRatesFromProvider(): void
    {
        $now           = new \DateTimeImmutable('2026-05-14T10:00:00+00:00');
        $effectiveDate = new \DateTimeImmutable('2026-05-14');

        $eur = CurrencyCode::fromString('EUR');
        $chf = CurrencyCode::fromString('CHF');
        $usd = CurrencyCode::fromString('USD');

        $rate1 = HistoricalRate::of(ExchangeRatePair::of($eur, $chf), '0.9523', $effectiveDate, 'ECB');
        $rate2 = HistoricalRate::of(ExchangeRatePair::of($eur, $usd), '1.0850', $effectiveDate, 'ECB');

        $provider = $this->createStub(ExchangeRateProvider::class);
        $provider->method('fetchSnapshot')->willReturn([$rate1, $rate2]);

        $repository = $this->createMock(ExchangeRateRepository::class);
        $repository->expects(self::exactly(2))->method('save');

        $uuidGenerator = $this->createStub(UuidGeneratorInterface::class);
        $uuidGenerator->method('generate')->willReturnOnConsecutiveCalls(
            '550e8400-e29b-41d4-a716-446655440001',
            '550e8400-e29b-41d4-a716-446655440002',
        );

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::exactly(2))->method('publish');

        $publisher = new DomainEventPublisher($eventBus);

        $handler = new ImportExchangeRatesHandler(
            $provider,
            $repository,
            $publisher,
            $uuidGenerator,
            $clock,
        );

        $handler(new ImportExchangeRates(date: $effectiveDate, source: 'ECB'));
    }
}
