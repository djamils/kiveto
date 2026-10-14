<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Command\Package;

use App\Context\Catalog\Application\Command\Package\AddPackageComponent\AddPackageComponent;
use App\Context\Catalog\Application\Command\Package\AddPackageComponent\AddPackageComponentHandler;
use App\Context\Catalog\Domain\Act\Repository\ActRepositoryInterface;
use App\Context\Catalog\Domain\Act\ValueObject\ActStatus;
use App\Context\Catalog\Domain\Article\Repository\ArticleRepositoryInterface;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleStatus;
use App\Context\Catalog\Domain\Package\Event\PackageComponentAdded;
use App\Context\Catalog\Domain\Package\Exception\ArchivedItemInPackageException;
use App\Context\Catalog\Domain\Package\Exception\PackageNotFoundException;
use App\Context\Catalog\Domain\Package\Package;
use App\Context\Catalog\Domain\Package\Repository\PackageRepositoryInterface;
use App\Context\Catalog\Domain\Shared\ValueObject\CatalogItemType;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Catalog\Application\Command\CatalogCommandFixtures as F;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AddPackageComponentHandler::class)]
#[CoversClass(AddPackageComponent::class)]
final class AddPackageComponentHandlerTest extends TestCase
{
    public function testAnActiveActJoinsThePackage(): void
    {
        $package = F::package();

        $actRepository = $this->createStub(ActRepositoryInterface::class);
        $actRepository->method('findById')->willReturn(F::act());

        $articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $articleRepository->expects(self::never())->method('findById');

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish')
            ->with([], self::isInstanceOf(PackageComponentAdded::class))
        ;

        $this->handler($this->packageRepositoryExpectingSave($package), $actRepository, $articleRepository, $eventBus)(
            new AddPackageComponent(F::PACKAGE_ID, F::CLINIC_ID, 'ACT', F::ACT_ID, 1, 10),
        );

        self::assertCount(1, $package->components());
        $component = $package->components()[0];
        self::assertSame(CatalogItemType::ACT, $component->itemRef()->type());
        self::assertSame(F::ACT_ID, $component->itemRef()->id());
        self::assertSame(1, $component->quantity()->value());
        self::assertSame(10, $component->weight());
    }

    public function testAnActiveArticleJoinsThePackage(): void
    {
        $package = F::package();

        $actRepository = $this->createMock(ActRepositoryInterface::class);
        $actRepository->expects(self::never())->method('findById');

        $articleRepository = $this->createStub(ArticleRepositoryInterface::class);
        $articleRepository->method('findById')->willReturn(F::consumable());

        $this->handler(
            $this->packageRepositoryExpectingSave($package),
            $actRepository,
            $articleRepository,
            $this->createStub(EventBusInterface::class),
        )(new AddPackageComponent(F::PACKAGE_ID, F::CLINIC_ID, 'ARTICLE', F::ARTICLE_ID, 3, 0));

        self::assertCount(1, $package->components());
        self::assertSame(CatalogItemType::ARTICLE, $package->components()[0]->itemRef()->type());
        self::assertSame(3, $package->components()[0]->quantity()->value());
    }

    public function testAnItemTheRepositoryCannotFindIsNotTreatedAsArchived(): void
    {
        $package = F::package();

        $actRepository = $this->createStub(ActRepositoryInterface::class);
        $actRepository->method('findById')->willReturn(null);

        $this->handler(
            $this->packageRepositoryExpectingSave($package),
            $actRepository,
            $this->createStub(ArticleRepositoryInterface::class),
            $this->createStub(EventBusInterface::class),
        )(new AddPackageComponent(F::PACKAGE_ID, F::CLINIC_ID, 'ACT', F::ACT_ID, 1, 0));

        self::assertCount(1, $package->components());
    }

    public function testAnArchivedActIsRefused(): void
    {
        $actRepository = $this->createStub(ActRepositoryInterface::class);
        $actRepository->method('findById')->willReturn(F::act(ActStatus::ARCHIVED));

        $this->expectException(ArchivedItemInPackageException::class);

        $this->handler(
            $this->packageRepositoryNeverSaving(F::package()),
            $actRepository,
            $this->createStub(ArticleRepositoryInterface::class),
            $this->createStub(EventBusInterface::class),
        )(new AddPackageComponent(F::PACKAGE_ID, F::CLINIC_ID, 'ACT', F::ACT_ID, 1, 0));
    }

    public function testAnArchivedArticleIsRefused(): void
    {
        $articleRepository = $this->createStub(ArticleRepositoryInterface::class);
        $articleRepository->method('findById')->willReturn(F::consumable(ArticleStatus::ARCHIVED));

        $this->expectException(ArchivedItemInPackageException::class);

        $this->handler(
            $this->packageRepositoryNeverSaving(F::package()),
            $this->createStub(ActRepositoryInterface::class),
            $articleRepository,
            $this->createStub(EventBusInterface::class),
        )(new AddPackageComponent(F::PACKAGE_ID, F::CLINIC_ID, 'ARTICLE', F::ARTICLE_ID, 1, 0));
    }

    public function testAnUnknownPackageIsRefused(): void
    {
        $this->expectException(PackageNotFoundException::class);

        $this->handler(
            $this->packageRepositoryNeverSaving(null),
            $this->createStub(ActRepositoryInterface::class),
            $this->createStub(ArticleRepositoryInterface::class),
            $this->createStub(EventBusInterface::class),
        )(new AddPackageComponent(F::PACKAGE_ID, F::CLINIC_ID, 'ACT', F::ACT_ID, 1, 0));
    }

    private function packageRepositoryExpectingSave(Package $package): PackageRepositoryInterface
    {
        $repository = $this->createMock(PackageRepositoryInterface::class);
        $repository->method('findById')->willReturn($package);
        $repository->expects(self::once())->method('save')->with($package);

        return $repository;
    }

    private function packageRepositoryNeverSaving(?Package $package): PackageRepositoryInterface
    {
        $repository = $this->createMock(PackageRepositoryInterface::class);
        $repository->method('findById')->willReturn($package);
        $repository->expects(self::never())->method('save');

        return $repository;
    }

    private function handler(
        PackageRepositoryInterface $packageRepository,
        ActRepositoryInterface $actRepository,
        ArticleRepositoryInterface $articleRepository,
        EventBusInterface $eventBus,
    ): AddPackageComponentHandler {
        return new AddPackageComponentHandler(
            $packageRepository,
            $actRepository,
            $articleRepository,
            new FrozenClock(F::now()),
            new DomainEventPublisher($eventBus),
        );
    }
}
