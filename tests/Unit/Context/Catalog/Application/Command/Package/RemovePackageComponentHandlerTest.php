<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Command\Package;

use App\Context\Catalog\Application\Command\Package\RemovePackageComponent\RemovePackageComponent;
use App\Context\Catalog\Application\Command\Package\RemovePackageComponent\RemovePackageComponentHandler;
use App\Context\Catalog\Domain\Package\Event\PackageComponentRemoved;
use App\Context\Catalog\Domain\Package\Exception\PackageNotFoundException;
use App\Context\Catalog\Domain\Package\PackageComponent;
use App\Context\Catalog\Domain\Package\Repository\PackageRepositoryInterface;
use App\Context\Catalog\Domain\Package\ValueObject\PackageComponentId;
use App\Context\Catalog\Domain\Package\ValueObject\Quantity;
use App\Context\Catalog\Domain\Shared\ValueObject\CatalogItemRef;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Catalog\Application\Command\CatalogCommandFixtures as F;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RemovePackageComponentHandler::class)]
#[CoversClass(RemovePackageComponent::class)]
final class RemovePackageComponentHandlerTest extends TestCase
{
    private const string KEPT_COMPONENT_ID    = '01960000-0000-7000-8000-00000000d001';
    private const string REMOVED_COMPONENT_ID = '01960000-0000-7000-8000-00000000d002';

    public function testTheComponentLeavesThePackage(): void
    {
        $package = F::package(components: [
            PackageComponent::create(
                PackageComponentId::fromString(self::KEPT_COMPONENT_ID),
                CatalogItemRef::fromPrimitives('ACT', F::ACT_ID),
                Quantity::of(1),
                0,
            ),
            PackageComponent::create(
                PackageComponentId::fromString(self::REMOVED_COMPONENT_ID),
                CatalogItemRef::fromPrimitives('ARTICLE', F::ARTICLE_ID),
                Quantity::of(2),
                0,
            ),
        ]);

        $repository = $this->createMock(PackageRepositoryInterface::class);
        $repository->method('findById')->willReturn($package);
        $repository->expects(self::once())->method('save')->with($package);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish')
            ->with([], self::isInstanceOf(PackageComponentRemoved::class))
        ;

        $this->handler($repository, $eventBus)(
            new RemovePackageComponent(F::PACKAGE_ID, self::REMOVED_COMPONENT_ID, F::CLINIC_ID),
        );

        self::assertCount(1, $package->components());
        self::assertSame(self::KEPT_COMPONENT_ID, $package->components()[0]->id()->toString());
    }

    public function testAnUnknownPackageIsRefused(): void
    {
        $repository = $this->createMock(PackageRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->expects(self::never())->method('save');

        $this->expectException(PackageNotFoundException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class))(
            new RemovePackageComponent(F::PACKAGE_ID, self::REMOVED_COMPONENT_ID, F::CLINIC_ID),
        );
    }

    private function handler(
        PackageRepositoryInterface $repository,
        EventBusInterface $eventBus,
    ): RemovePackageComponentHandler {
        return new RemovePackageComponentHandler(
            $repository,
            new FrozenClock(F::now()),
            new DomainEventPublisher($eventBus),
        );
    }
}
