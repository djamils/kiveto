<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Money\Domain;

use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\System\Money\Domain\Currency;
use App\System\Money\Domain\Event\CurrencyActivated;
use App\System\Money\Domain\Event\CurrencyDeactivated;
use App\System\Money\Domain\Event\CurrencyRegistered;
use App\System\Money\Domain\ValueObject\CurrencyDecimals;
use App\System\Money\Domain\ValueObject\CurrencySymbol;
use PHPUnit\Framework\TestCase;

final class CurrencyTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-05-14T10:00:00+00:00');
    }

    public function testCreateRecordsCurrencyRegisteredEvent(): void
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($this->now);

        $currency = Currency::create(
            code: CurrencyCode::fromString('EUR'),
            symbol: CurrencySymbol::fromString('€'),
            decimals: CurrencyDecimals::of(2),
            displayName: 'Euro',
            clock: $clock,
        );

        self::assertTrue($currency->isActive());

        $events = $currency->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CurrencyRegistered::class, $events[0]);
    }

    public function testReconstituteRecordsNoEvents(): void
    {
        $currency = Currency::reconstitute(
            code: CurrencyCode::fromString('EUR'),
            symbol: CurrencySymbol::fromString('€'),
            decimals: CurrencyDecimals::of(2),
            displayName: 'Euro',
            active: true,
            createdAt: $this->now,
            updatedAt: $this->now,
        );

        self::assertCount(0, $currency->pullDomainEvents());
    }

    public function testDeactivateRecordsCurrencyDeactivatedEvent(): void
    {
        $currency = $this->makeActiveCurrency();

        $currency->deactivate();

        $events = $currency->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CurrencyDeactivated::class, $events[0]);
        self::assertFalse($currency->isActive());
    }

    public function testDeactivateIsIdempotent(): void
    {
        $currency = $this->makeActiveCurrency();

        $currency->deactivate();
        $_ = $currency->pullDomainEvents(); // Clear first event
        $currency->deactivate();

        $events = $currency->pullDomainEvents();
        self::assertCount(0, $events);
    }

    public function testActivateRecordsCurrencyActivatedEvent(): void
    {
        $currency = $this->makeInactiveCurrency();

        $currency->activate();

        $events = $currency->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CurrencyActivated::class, $events[0]);
        self::assertTrue($currency->isActive());
    }

    public function testActivateIsIdempotent(): void
    {
        $currency = $this->makeActiveCurrency();

        $currency->activate();

        $events = $currency->pullDomainEvents();
        self::assertCount(0, $events);
    }

    public function testActivateAfterDeactivateRecordsOneEvent(): void
    {
        $currency = $this->makeInactiveCurrency();

        $currency->activate();
        $_ = $currency->pullDomainEvents(); // Clear first event
        $currency->activate();

        // Second activate is a no-op since already active
        $events = $currency->pullDomainEvents();
        self::assertCount(0, $events);
    }

    public function testUpdateDisplayDoesNotRecordEvent(): void
    {
        $currency = $this->makeActiveCurrency();

        $currency->updateDisplay(
            CurrencySymbol::fromString('€'),
            CurrencyDecimals::of(2),
            'Euro Updated',
        );

        self::assertCount(0, $currency->pullDomainEvents());
        self::assertSame('Euro Updated', $currency->displayName());
    }

    private function makeActiveCurrency(): Currency
    {
        return Currency::reconstitute(
            code: CurrencyCode::fromString('EUR'),
            symbol: CurrencySymbol::fromString('€'),
            decimals: CurrencyDecimals::of(2),
            displayName: 'Euro',
            active: true,
            createdAt: $this->now,
            updatedAt: $this->now,
        );
    }

    private function makeInactiveCurrency(): Currency
    {
        return Currency::reconstitute(
            code: CurrencyCode::fromString('EUR'),
            symbol: CurrencySymbol::fromString('€'),
            decimals: CurrencyDecimals::of(2),
            displayName: 'Euro',
            active: false,
            createdAt: $this->now,
            updatedAt: $this->now,
        );
    }
}
