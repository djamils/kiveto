<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Command\Package;

use App\Context\Catalog\Application\Command\Package\ChangePackageFixedPrice\ChangePackageFixedPrice;
use App\Context\Catalog\Application\Command\Package\ChangePackageFixedPrice\ChangePackageFixedPriceHandler;
use App\Context\Catalog\Domain\Package\Event\PackageFixedPriceChanged;
use App\Context\Catalog\Domain\Package\Exception\PackageNotFoundException;
use App\Context\Catalog\Domain\Package\Repository\PackageRepositoryInterface;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Catalog\Application\Command\CatalogCommandFixtures as F;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChangePackageFixedPriceHandler::class)]
#[CoversClass(ChangePackageFixedPrice::class)]
final class ChangePackageFixedPriceHandlerTest extends TestCase
{
    public function testThePackageTakesItsNewFixedPrice(): void
    {
        $package = F::package();

        $repository = $this->createMock(PackageRepositoryInterface::class);
        $repository->method('findById')->willReturn($package);
        $repository->expects(self::once())->method('save')->with($package);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish')
            ->with([], self::isInstanceOf(PackageFixedPriceChanged::class))
        ;

        $this->handler($repository, $eventBus)(
            new ChangePackageFixedPrice(F::PACKAGE_ID, F::CLINIC_ID, 27500, 'EUR', 'incl'),
        );

        self::assertSame(27500, $package->fixedPrice()?->minorUnits());
    }

    public function testAnUnknownPackageIsRefused(): void
    {
        $repository = $this->createMock(PackageRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->expects(self::never())->method('save');

        $this->expectException(PackageNotFoundException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class))(
            new ChangePackageFixedPrice(F::PACKAGE_ID, F::CLINIC_ID, 27500, 'EUR', 'incl'),
        );
    }

    private function handler(
        PackageRepositoryInterface $repository,
        EventBusInterface $eventBus,
    ): ChangePackageFixedPriceHandler {
        return new ChangePackageFixedPriceHandler(
            $repository,
            new FrozenClock(F::now()),
            new DomainEventPublisher($eventBus),
        );
    }
}
