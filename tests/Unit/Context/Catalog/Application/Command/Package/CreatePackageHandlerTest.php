<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Command\Package;

use App\Context\Catalog\Application\Command\Package\CreatePackage\CreatePackage;
use App\Context\Catalog\Application\Command\Package\CreatePackage\CreatePackageHandler;
use App\Context\Catalog\Domain\Exception\InvalidPriceEntryModeException;
use App\Context\Catalog\Domain\Package\Event\PackageCreated;
use App\Context\Catalog\Domain\Package\Exception\DuplicatePackageCodeException;
use App\Context\Catalog\Domain\Package\Package;
use App\Context\Catalog\Domain\Package\Repository\PackageRepositoryInterface;
use App\Context\Catalog\Domain\Package\ValueObject\PackagePricingMode;
use App\Context\Catalog\Domain\ValueObject\PriceEntryMode;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Catalog\Application\Command\CatalogCommandFixtures as F;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreatePackageHandler::class)]
#[CoversClass(CreatePackage::class)]
final class CreatePackageHandlerTest extends TestCase
{
    public function testAFixedPricePackageIsCreatedWithItsPrice(): void
    {
        $saved      = null;
        $repository = $this->createMock(PackageRepositoryInterface::class);
        $repository->method('existsByCode')->willReturn(false);
        $repository->expects(self::once())->method('save')
            ->willReturnCallback(static function (Package $package) use (&$saved): void {
                $saved = $package;
            })
        ;

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish')
            ->with([], self::isInstanceOf(PackageCreated::class))
        ;

        $id = $this->handler($repository, $eventBus)($this->command('FIXED_PRICE', 25000, 'EUR'));

        self::assertSame(F::GENERATED_ID, $id);
        self::assertInstanceOf(Package::class, $saved);
        self::assertSame(PackagePricingMode::FIXED_PRICE, $saved->pricingMode());
        self::assertSame(25000, $saved->fixedPrice()?->minorUnits());
        self::assertSame(PriceEntryMode::INCLUDING_TAX, $saved->fixedPrice()->mode());
        self::assertSame([], $saved->components());
    }

    public function testAFixedPriceWithoutItsModeIsRefused(): void
    {
        $repository = $this->createMock(PackageRepositoryInterface::class);
        $repository->method('existsByCode')->willReturn(false);
        $repository->expects(self::never())->method('save');

        $this->expectException(InvalidPriceEntryModeException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class))(
            $this->command('FIXED_PRICE', 25000, 'EUR', null),
        );
    }

    public function testASumOfComponentsPackageIsCreatedWithoutPrice(): void
    {
        $saved      = null;
        $repository = $this->createMock(PackageRepositoryInterface::class);
        $repository->method('existsByCode')->willReturn(false);
        $repository->expects(self::once())->method('save')
            ->willReturnCallback(static function (Package $package) use (&$saved): void {
                $saved = $package;
            })
        ;

        $this->handler($repository, $this->createStub(EventBusInterface::class))(
            $this->command('SUM_OF_COMPONENTS', null, null),
        );

        self::assertInstanceOf(Package::class, $saved);
        self::assertSame(PackagePricingMode::SUM_OF_COMPONENTS, $saved->pricingMode());
        self::assertNull($saved->fixedPrice());
    }

    public function testAPriceAmountWithoutCurrencyIsIgnored(): void
    {
        $saved      = null;
        $repository = $this->createMock(PackageRepositoryInterface::class);
        $repository->method('existsByCode')->willReturn(false);
        $repository->expects(self::once())->method('save')
            ->willReturnCallback(static function (Package $package) use (&$saved): void {
                $saved = $package;
            })
        ;

        $this->handler($repository, $this->createStub(EventBusInterface::class))(
            $this->command('SUM_OF_COMPONENTS', 25000, null),
        );

        self::assertInstanceOf(Package::class, $saved);
        self::assertNull($saved->fixedPrice());
    }

    public function testADuplicateCodeIsRefused(): void
    {
        $repository = $this->createMock(PackageRepositoryInterface::class);
        $repository->method('existsByCode')->willReturn(true);
        $repository->expects(self::never())->method('save');

        $this->expectException(DuplicatePackageCodeException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class))(
            $this->command('FIXED_PRICE', 25000, 'EUR'),
        );
    }

    private function command(string $pricingMode, ?int $minorUnits, ?string $currency, ?string $mode = 'incl'): CreatePackage
    {
        return new CreatePackage(
            clinicId: F::CLINIC_ID,
            name: 'Forfait stérilisation chatte',
            code: 'PKG-STER',
            taxCategoryCode: 'veterinary.act.surgery',
            pricingMode: $pricingMode,
            fixedPriceMinorUnits: $minorUnits,
            fixedPriceCurrency: $currency,
            fixedPriceMode: $mode,
        );
    }

    private function handler(PackageRepositoryInterface $repository, EventBusInterface $eventBus): CreatePackageHandler
    {
        $uuidGenerator = $this->createStub(UuidGeneratorInterface::class);
        $uuidGenerator->method('generate')->willReturn(F::GENERATED_ID);

        return new CreatePackageHandler(
            $repository,
            $uuidGenerator,
            new FrozenClock(F::now()),
            new DomainEventPublisher($eventBus),
        );
    }
}
