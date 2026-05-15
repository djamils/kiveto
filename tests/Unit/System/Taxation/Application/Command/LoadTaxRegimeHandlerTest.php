<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Taxation\Application\Command;

use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\System\Taxation\Application\Command\LoadTaxRegime\LoadTaxRegime;
use App\System\Taxation\Application\Command\LoadTaxRegime\LoadTaxRegimeHandler;
use App\System\Taxation\Application\Port\TaxRegimeBlueprint;
use App\System\Taxation\Application\Port\TaxRegimeLoaderInterface;
use App\System\Taxation\Domain\Entity\TaxRate;
use App\System\Taxation\Domain\Repository\MentionTemplateRepositoryInterface;
use App\System\Taxation\Domain\Repository\TaxRegimeRepositoryInterface;
use App\System\Taxation\Domain\TaxRegime;
use App\System\Taxation\Domain\ValueObject\TaxRateCondition;
use App\System\Taxation\Domain\ValueObject\TaxRateId;
use App\System\Taxation\Domain\ValueObject\TaxRateValue;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use App\System\Taxation\Domain\ValueObject\ValidityPeriod;
use PHPUnit\Framework\TestCase;

final class LoadTaxRegimeHandlerTest extends TestCase
{
    private \DateTimeImmutable $now;
    private ClockInterface $clock;

    protected function setUp(): void
    {
        $this->now   = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');
        $this->clock = $this->createStub(ClockInterface::class);
        $this->clock->method('now')->willReturn($this->now);
    }

    public function testCreatesRegimeWhenNotFound(): void
    {
        $regimeId  = TaxRegimeId::fromString('FR');
        $blueprint = $this->makeBlueprint($regimeId);

        $loader = $this->createStub(TaxRegimeLoaderInterface::class);
        $loader->method('load')->willReturn($blueprint);

        $regimeRepo = $this->createMock(TaxRegimeRepositoryInterface::class);
        $regimeRepo->method('findById')->willReturn(null);
        $regimeRepo->expects(self::once())->method('save');

        $mentionRepo = $this->createMock(MentionTemplateRepositoryInterface::class);
        $mentionRepo->expects(self::once())->method('deleteByRegime');

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish');

        $publisher = new DomainEventPublisher($eventBus);

        $handler = new LoadTaxRegimeHandler($regimeRepo, $mentionRepo, $loader, $publisher, $this->clock);
        $handler(new LoadTaxRegime($regimeId));
    }

    public function testIdempotentReload(): void
    {
        $regimeId = TaxRegimeId::fromString('FR');

        $existingRegime = TaxRegime::reconstitute(
            $regimeId,
            'Régime TVA France',
            CountryCode::fromString('FR'),
            [],
            true,
            $this->now,
            $this->now,
        );

        // Pull events so it starts clean
        $_ = $existingRegime->pullDomainEvents();

        $blueprint = $this->makeBlueprint($regimeId);

        $loader = $this->createStub(TaxRegimeLoaderInterface::class);
        $loader->method('load')->willReturn($blueprint);

        $regimeRepo = $this->createMock(TaxRegimeRepositoryInterface::class);
        $regimeRepo->method('findById')->willReturn($existingRegime);
        $regimeRepo->expects(self::once())->method('save');

        $mentionRepo = $this->createMock(MentionTemplateRepositoryInterface::class);
        $mentionRepo->expects(self::once())->method('deleteByRegime');

        $eventBus  = $this->createStub(EventBusInterface::class);
        $publisher = new DomainEventPublisher($eventBus);

        $handler = new LoadTaxRegimeHandler($regimeRepo, $mentionRepo, $loader, $publisher, $this->clock);
        $handler(new LoadTaxRegime($regimeId));

        // After replaceAllRates on existing active regime, it still has one rate
        self::assertCount(1, $existingRegime->rates());
    }

    public function testPublishCalledOnce(): void
    {
        $regimeId  = TaxRegimeId::fromString('FR');
        $blueprint = $this->makeBlueprint($regimeId);

        $loader = $this->createStub(TaxRegimeLoaderInterface::class);
        $loader->method('load')->willReturn($blueprint);

        $regimeRepo = $this->createStub(TaxRegimeRepositoryInterface::class);
        $regimeRepo->method('findById')->willReturn(null);

        $mentionRepo = $this->createStub(MentionTemplateRepositoryInterface::class);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish');

        $publisher = new DomainEventPublisher($eventBus);

        $handler = new LoadTaxRegimeHandler($regimeRepo, $mentionRepo, $loader, $publisher, $this->clock);
        $handler(new LoadTaxRegime($regimeId));
    }

    private function makeRate(): TaxRate
    {
        return TaxRate::of(
            TaxRateId::fromString('fr.standard'),
            TaxRateValue::ofBasisPoints(2000),
            ValidityPeriod::openEndedFrom(new \DateTimeImmutable('2020-01-01')),
            TaxRateCondition::of([], [], [], [], null),
        );
    }

    private function makeBlueprint(TaxRegimeId $regimeId, bool $active = true): TaxRegimeBlueprint
    {
        return new TaxRegimeBlueprint(
            $regimeId,
            'Régime TVA France',
            CountryCode::fromString('FR'),
            $active,
            [$this->makeRate()],
            [],
        );
    }
}
