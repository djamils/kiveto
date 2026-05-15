<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Taxation\Application\Command;

use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Taxation\Application\Command\RegisterTaxCategory\RegisterTaxCategory;
use App\System\Taxation\Application\Command\RegisterTaxCategory\RegisterTaxCategoryHandler;
use App\System\Taxation\Domain\Repository\TaxCategoryRepositoryInterface;
use App\System\Taxation\Domain\TaxCategory;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use PHPUnit\Framework\TestCase;

final class RegisterTaxCategoryHandlerTest extends TestCase
{
    private \DateTimeImmutable $now;
    private ClockInterface $clock;

    protected function setUp(): void
    {
        $this->now   = new \DateTimeImmutable('2024-01-01T10:00:00+00:00');
        $this->clock = $this->createStub(ClockInterface::class);
        $this->clock->method('now')->willReturn($this->now);
    }

    public function testNewCategoryCreatedAndPublished(): void
    {
        $code    = TaxCategoryCode::fromString('veterinary.act.consultation');
        $command = new RegisterTaxCategory($code, 'Acte de consultation', null);

        $categoryRepo = $this->createMock(TaxCategoryRepositoryInterface::class);
        $categoryRepo->method('findByCode')->willReturn(null);
        $categoryRepo->expects(self::once())->method('save');

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish');

        $publisher = new DomainEventPublisher($eventBus);

        $handler = new RegisterTaxCategoryHandler($categoryRepo, $publisher, $this->clock);
        $handler($command);
    }

    public function testExistingCategoryUpdated(): void
    {
        $code = TaxCategoryCode::fromString('veterinary.act.consultation');

        $existing = TaxCategory::reconstitute(
            $code,
            'Old name',
            null,
            true,
            $this->now,
            $this->now,
        );

        $command = new RegisterTaxCategory($code, 'New name', 'New description');

        $categoryRepo = $this->createMock(TaxCategoryRepositoryInterface::class);
        $categoryRepo->method('findByCode')->willReturn($existing);
        $categoryRepo->expects(self::once())->method('save');

        $eventBus = $this->createMock(EventBusInterface::class);
        // For existing category, pullDomainEvents() returns [] → publish is never called
        $eventBus->expects(self::never())->method('publish');

        $publisher = new DomainEventPublisher($eventBus);

        $handler = new RegisterTaxCategoryHandler($categoryRepo, $publisher, $this->clock);
        $handler($command);

        self::assertSame('New name', $existing->displayName());
        self::assertSame('New description', $existing->description());
    }
}
