<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Command\Act;

use App\Context\Catalog\Application\Command\Act\ChangeActTaxCategory\ChangeActTaxCategory;
use App\Context\Catalog\Application\Command\Act\ChangeActTaxCategory\ChangeActTaxCategoryHandler;
use App\Context\Catalog\Domain\Act\Event\ActTaxCategoryChanged;
use App\Context\Catalog\Domain\Act\Exception\ActNotFoundException;
use App\Context\Catalog\Domain\Act\Repository\ActRepositoryInterface;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\System\Taxation\Domain\Service\TaxCategoryRegistryInterface;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Catalog\Application\Command\CatalogCommandFixtures as F;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChangeActTaxCategoryHandler::class)]
#[CoversClass(ChangeActTaxCategory::class)]
final class ChangeActTaxCategoryHandlerTest extends TestCase
{
    public function testTheActMovesToTheNewTaxCategory(): void
    {
        $act = F::act();

        $repository = $this->createMock(ActRepositoryInterface::class);
        $repository->method('findById')->willReturn($act);
        $repository->expects(self::once())->method('save')->with($act);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish')
            ->with([], self::isInstanceOf(ActTaxCategoryChanged::class))
        ;

        $this->handler($repository, $eventBus, true)(
            new ChangeActTaxCategory(F::ACT_ID, F::CLINIC_ID, 'veterinary.act.surgery'),
        );

        self::assertSame('veterinary.act.surgery', $act->taxCategory()->toString());
    }

    public function testAnUnknownTaxCategoryIsRefused(): void
    {
        $repository = $this->createMock(ActRepositoryInterface::class);
        $repository->expects(self::never())->method('findById');
        $repository->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Unknown tax category code: "veterinary.unknown".');

        $this->handler($repository, $this->createStub(EventBusInterface::class), false)(
            new ChangeActTaxCategory(F::ACT_ID, F::CLINIC_ID, 'veterinary.unknown'),
        );
    }

    public function testAnUnknownActIsRefused(): void
    {
        $repository = $this->createMock(ActRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->expects(self::never())->method('save');

        $this->expectException(ActNotFoundException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class), true)(
            new ChangeActTaxCategory(F::ACT_ID, F::CLINIC_ID, 'veterinary.act.surgery'),
        );
    }

    private function handler(
        ActRepositoryInterface $repository,
        EventBusInterface $eventBus,
        bool $taxCategoryKnown,
    ): ChangeActTaxCategoryHandler {
        $registry = $this->createStub(TaxCategoryRegistryInterface::class);
        $registry->method('has')->willReturn($taxCategoryKnown);

        return new ChangeActTaxCategoryHandler(
            $repository,
            new FrozenClock(F::now()),
            new DomainEventPublisher($eventBus),
            $registry,
        );
    }
}
