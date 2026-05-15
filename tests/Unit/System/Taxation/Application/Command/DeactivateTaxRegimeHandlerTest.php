<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Taxation\Application\Command;

use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\System\Taxation\Application\Command\DeactivateTaxRegime\DeactivateTaxRegime;
use App\System\Taxation\Application\Command\DeactivateTaxRegime\DeactivateTaxRegimeHandler;
use App\System\Taxation\Domain\Exception\UnknownTaxRegimeException;
use App\System\Taxation\Domain\Repository\TaxRegimeRepositoryInterface;
use App\System\Taxation\Domain\TaxRegime;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use PHPUnit\Framework\TestCase;

final class DeactivateTaxRegimeHandlerTest extends TestCase
{
    private \DateTimeImmutable $now;
    private ClockInterface $clock;

    protected function setUp(): void
    {
        $this->now   = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');
        $this->clock = $this->createStub(ClockInterface::class);
        $this->clock->method('now')->willReturn($this->now);
    }

    public function testDeactivatesRegimeSavesAndPublishes(): void
    {
        $regimeId = TaxRegimeId::fromString('FR');

        $activeRegime = TaxRegime::reconstitute(
            $regimeId,
            'Régime TVA France',
            CountryCode::fromString('FR'),
            [],
            true,
            $this->now,
            $this->now,
        );
        // Clear reconstitute events (none, but be safe)
        $_ = $activeRegime->pullDomainEvents();

        $regimeRepo = $this->createMock(TaxRegimeRepositoryInterface::class);
        $regimeRepo->method('findById')->willReturn($activeRegime);
        $regimeRepo->expects(self::once())->method('save');

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish');

        $publisher = new DomainEventPublisher($eventBus);

        $handler = new DeactivateTaxRegimeHandler($regimeRepo, $publisher, $this->clock);
        $handler(new DeactivateTaxRegime($regimeId));

        self::assertFalse($activeRegime->isActive());
    }

    public function testUnknownRegimeThrows(): void
    {
        $regimeId = TaxRegimeId::fromString('FR');

        $regimeRepo = $this->createStub(TaxRegimeRepositoryInterface::class);
        $regimeRepo->method('findById')->willReturn(null);

        $eventBus  = $this->createStub(EventBusInterface::class);
        $publisher = new DomainEventPublisher($eventBus);

        $handler = new DeactivateTaxRegimeHandler($regimeRepo, $publisher, $this->clock);

        $this->expectException(UnknownTaxRegimeException::class);

        $handler(new DeactivateTaxRegime($regimeId));
    }
}
