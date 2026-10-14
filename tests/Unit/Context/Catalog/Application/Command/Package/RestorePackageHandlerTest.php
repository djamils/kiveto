<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Command\Package;

use App\Context\Catalog\Application\Command\Package\RestorePackage\RestorePackage;
use App\Context\Catalog\Application\Command\Package\RestorePackage\RestorePackageHandler;
use App\Context\Catalog\Domain\Package\Event\PackageRestored;
use App\Context\Catalog\Domain\Package\Exception\PackageNotFoundException;
use App\Context\Catalog\Domain\Package\Repository\PackageRepositoryInterface;
use App\Context\Catalog\Domain\Package\ValueObject\PackagePricingMode;
use App\Context\Catalog\Domain\Package\ValueObject\PackageStatus;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Catalog\Application\Command\CatalogCommandFixtures as F;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RestorePackageHandler::class)]
#[CoversClass(RestorePackage::class)]
final class RestorePackageHandlerTest extends TestCase
{
    public function testThePackageIsRestoredSavedAndAnnounced(): void
    {
        $package = F::package(PackagePricingMode::FIXED_PRICE, PackageStatus::ARCHIVED);

        $repository = $this->createMock(PackageRepositoryInterface::class);
        $repository->method('findById')->willReturn($package);
        $repository->expects(self::once())->method('save')->with($package);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish')
            ->with([], self::isInstanceOf(PackageRestored::class))
        ;

        $this->handler($repository, $eventBus)(new RestorePackage(F::PACKAGE_ID, F::CLINIC_ID));

        self::assertSame(PackageStatus::ACTIVE, $package->status());
    }

    public function testAnUnknownPackageIsRefused(): void
    {
        $repository = $this->createMock(PackageRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->expects(self::never())->method('save');

        $this->expectException(PackageNotFoundException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class))(
            new RestorePackage(F::PACKAGE_ID, F::CLINIC_ID),
        );
    }

    private function handler(PackageRepositoryInterface $repository, EventBusInterface $eventBus): RestorePackageHandler
    {
        return new RestorePackageHandler($repository, new FrozenClock(F::now()), new DomainEventPublisher($eventBus));
    }
}
