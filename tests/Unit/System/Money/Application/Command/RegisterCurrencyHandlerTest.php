<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Money\Application\Command;

use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Application\Command\RegisterCurrency\RegisterCurrency;
use App\System\Money\Application\Command\RegisterCurrency\RegisterCurrencyHandler;
use App\System\Money\Domain\Currency;
use App\System\Money\Domain\Repository\CurrencyRepository;
use App\System\Money\Domain\ValueObject\CurrencyDecimals;
use App\System\Money\Domain\ValueObject\CurrencySymbol;
use PHPUnit\Framework\TestCase;

final class RegisterCurrencyHandlerTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-05-14T10:00:00+00:00');
    }

    public function testCreatesCurrencyWhenNotFound(): void
    {
        $repository = $this->createMock(CurrencyRepository::class);
        $repository->method('findByCode')->willReturn(null);
        $repository->expects(self::once())->method('save');

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish');

        $publisher = new DomainEventPublisher($eventBus);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($this->now);

        $handler = new RegisterCurrencyHandler($repository, $publisher, $clock);

        $handler(new RegisterCurrency(
            code: 'EUR',
            symbol: '€',
            decimals: 2,
            displayName: 'Euro',
        ));
    }

    public function testUpdatesCurrencyWhenAlreadyExists(): void
    {
        $existing = Currency::reconstitute(
            code: CurrencyCode::fromString('EUR'),
            symbol: CurrencySymbol::fromString('€'),
            decimals: CurrencyDecimals::of(2),
            displayName: 'Euro',
            active: true,
            createdAt: $this->now,
            updatedAt: $this->now,
        );

        $repository = $this->createMock(CurrencyRepository::class);
        $repository->method('findByCode')->willReturn($existing);
        $repository->expects(self::once())->method('save');

        // When updating, pullDomainEvents() returns [] so publish is never called
        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::never())->method('publish');

        $publisher = new DomainEventPublisher($eventBus);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($this->now);

        $handler = new RegisterCurrencyHandler($repository, $publisher, $clock);

        $handler(new RegisterCurrency(
            code: 'EUR',
            symbol: '€',
            decimals: 2,
            displayName: 'Euro Updated',
        ));

        self::assertSame('Euro Updated', $existing->displayName());
    }
}
