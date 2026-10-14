<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Command\Package;

use App\Context\Catalog\Application\Command\Package\RevisePackage\RevisePackage;
use App\Context\Catalog\Application\Command\Package\RevisePackage\RevisePackageHandler;
use App\Context\Catalog\Application\Port\ClinicInfoProviderInterface;
use App\Context\Catalog\Domain\Exception\ClinicCurrencyMismatchException;
use App\Context\Catalog\Domain\Package\Event\PackageRevised;
use App\Context\Catalog\Domain\Package\Exception\InvalidPackagePricingModeException;
use App\Context\Catalog\Domain\Package\Exception\PackageNotFoundException;
use App\Context\Catalog\Domain\Package\Package;
use App\Context\Catalog\Domain\Package\Repository\PackageRepositoryInterface;
use App\Context\Catalog\Domain\Package\ValueObject\PackageCode;
use App\Context\Catalog\Domain\Package\ValueObject\PackageId;
use App\Context\Catalog\Domain\Package\ValueObject\PackageName;
use App\Context\Catalog\Domain\Package\ValueObject\PackagePricingMode;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\Service\TaxCategoryRegistryInterface;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RevisePackageHandler::class)]
#[CoversClass(RevisePackage::class)]
#[CoversClass(PackageRevised::class)]
#[CoversClass(Package::class)]
final class RevisePackageHandlerTest extends TestCase
{
    private const string CLINIC_ID  = '01960000-0000-7000-8000-000000000d01';
    private const string PACKAGE_ID = '01960000-0000-7000-8000-000000000d02';
    private const string TAX        = 'veterinary.act.preventive';

    public function testAPackageTakesItsNewNameFamilyAndFixedPrice(): void
    {
        $package = $this->package(PackagePricingMode::SUM_OF_COMPONENTS, null);

        $repository = $this->createMock(PackageRepositoryInterface::class);
        $repository->method('findById')->willReturn($package);
        $repository->expects(self::once())->method('save')->with($package);

        $published = [];
        $eventBus  = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish')->willReturnCallback(
            static function (array $stamps, object ...$events) use (&$published): void {
                array_push($published, ...$events);
            },
        );

        $this->handler($repository, $eventBus)(new RevisePackage(
            packageId: self::PACKAGE_ID,
            clinicId: self::CLINIC_ID,
            name: 'Pack chaton',
            taxCategoryCode: 'veterinary.act.vaccination',
            pricingMode: 'FIXED_PRICE',
            fixedPriceMinorUnits: 13750,
            fixedPriceCurrency: 'EUR',
        ));

        self::assertSame('Pack chaton', $package->name()->toString());
        self::assertSame('veterinary.act.vaccination', $package->taxCategory()->toString());
        self::assertSame(PackagePricingMode::FIXED_PRICE, $package->pricingMode());
        self::assertSame(13750, $package->fixedPrice()?->minorUnits());

        $event = $published[0];
        self::assertInstanceOf(PackageRevised::class, $event);
        self::assertSame(self::PACKAGE_ID, $event->aggregateId());
        self::assertSame([
            'packageId'            => self::PACKAGE_ID,
            'clinicId'             => self::CLINIC_ID,
            'name'                 => 'Pack chaton',
            'taxCategoryCode'      => 'veterinary.act.vaccination',
            'pricingMode'          => 'FIXED_PRICE',
            'fixedPriceMinorUnits' => 13750,
        ], $event->payload());
    }

    public function testGoingBackToTheSumDropsTheFixedPrice(): void
    {
        $package = $this->package(PackagePricingMode::FIXED_PRICE, 16500);

        $repository = $this->createStub(PackageRepositoryInterface::class);
        $repository->method('findById')->willReturn($package);

        $this->handler($repository, $this->createStub(EventBusInterface::class))(new RevisePackage(
            packageId: self::PACKAGE_ID,
            clinicId: self::CLINIC_ID,
            name: 'Pack chiot',
            taxCategoryCode: self::TAX,
            pricingMode: 'SUM_OF_COMPONENTS',
            fixedPriceMinorUnits: null,
            fixedPriceCurrency: null,
        ));

        self::assertSame(PackagePricingMode::SUM_OF_COMPONENTS, $package->pricingMode());
        self::assertNull($package->fixedPrice());
    }

    public function testTheSameFaceRecordsNothing(): void
    {
        foreach ([[PackagePricingMode::FIXED_PRICE, 16500], [PackagePricingMode::SUM_OF_COMPONENTS, null]] as [$mode, $price]) {
            $package = $this->package($mode, $price);

            $repository = $this->createStub(PackageRepositoryInterface::class);
            $repository->method('findById')->willReturn($package);

            $eventBus = $this->createMock(EventBusInterface::class);
            $eventBus->expects(self::never())->method('publish');

            $this->handler($repository, $eventBus)(new RevisePackage(
                packageId: self::PACKAGE_ID,
                clinicId: self::CLINIC_ID,
                name: 'Pack chiot',
                taxCategoryCode: self::TAX,
                pricingMode: $mode->value,
                fixedPriceMinorUnits: $price,
                fixedPriceCurrency: null !== $price ? 'EUR' : null,
            ));
        }
    }

    public function testAFixedPriceGivenToASumPackageIsAChange(): void
    {
        $package = $this->package(PackagePricingMode::FIXED_PRICE, 16500);

        $package->revise(
            PackageName::fromString('Pack chiot'),
            TaxCategoryCode::fromString(self::TAX),
            PackagePricingMode::FIXED_PRICE,
            Money::fromMinorUnits(17000, CurrencyCode::fromString('EUR')),
            new \DateTimeImmutable('2026-08-22'),
        );

        self::assertSame(17000, $package->fixedPrice()?->minorUnits());
    }

    public function testTheFixedModeNeedsAPrice(): void
    {
        $this->expectException(InvalidPackagePricingModeException::class);

        $this->package(PackagePricingMode::SUM_OF_COMPONENTS, null)->revise(
            PackageName::fromString('Pack chiot'),
            TaxCategoryCode::fromString(self::TAX),
            PackagePricingMode::FIXED_PRICE,
            null,
            new \DateTimeImmutable('2026-08-22'),
        );
    }

    public function testTheSumModeRefusesAPrice(): void
    {
        $this->expectException(InvalidPackagePricingModeException::class);

        $this->package(PackagePricingMode::FIXED_PRICE, 16500)->revise(
            PackageName::fromString('Pack chiot'),
            TaxCategoryCode::fromString(self::TAX),
            PackagePricingMode::SUM_OF_COMPONENTS,
            Money::fromMinorUnits(100, CurrencyCode::fromString('EUR')),
            new \DateTimeImmutable('2026-08-22'),
        );
    }

    public function testAnUnknownTaxCategoryIsRefused(): void
    {
        $repository = $this->createMock(PackageRepositoryInterface::class);
        $repository->method('findById')->willReturn($this->package(PackagePricingMode::SUM_OF_COMPONENTS, null));
        $repository->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Unknown tax category code');

        $this->handler($repository, $this->createStub(EventBusInterface::class), knownTax: false)(new RevisePackage(
            packageId: self::PACKAGE_ID,
            clinicId: self::CLINIC_ID,
            name: 'Pack chiot',
            taxCategoryCode: 'veterinary.unknown',
            pricingMode: 'SUM_OF_COMPONENTS',
            fixedPriceMinorUnits: null,
            fixedPriceCurrency: null,
        ));
    }

    public function testAPriceInAnotherCurrencyIsRefused(): void
    {
        $repository = $this->createStub(PackageRepositoryInterface::class);
        $repository->method('findById')->willReturn($this->package(PackagePricingMode::SUM_OF_COMPONENTS, null));

        $this->expectException(ClinicCurrencyMismatchException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class))(new RevisePackage(
            packageId: self::PACKAGE_ID,
            clinicId: self::CLINIC_ID,
            name: 'Pack chiot',
            taxCategoryCode: self::TAX,
            pricingMode: 'FIXED_PRICE',
            fixedPriceMinorUnits: 1000,
            fixedPriceCurrency: null,
        ));
    }

    public function testAnUnknownPackageIsRefused(): void
    {
        $repository = $this->createStub(PackageRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $this->expectException(PackageNotFoundException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class))(new RevisePackage(
            packageId: self::PACKAGE_ID,
            clinicId: self::CLINIC_ID,
            name: 'Pack chiot',
            taxCategoryCode: self::TAX,
            pricingMode: 'SUM_OF_COMPONENTS',
            fixedPriceMinorUnits: null,
            fixedPriceCurrency: null,
        ));
    }

    private function handler(
        PackageRepositoryInterface $repository,
        EventBusInterface $eventBus,
        bool $knownTax = true,
    ): RevisePackageHandler {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-08-22 10:00:00'));

        $taxes = $this->createStub(TaxCategoryRegistryInterface::class);
        $taxes->method('has')->willReturn($knownTax);

        $clinic = $this->createStub(ClinicInfoProviderInterface::class);
        $clinic->method('getCurrencyCode')->willReturn(CurrencyCode::fromString('EUR'));

        return new RevisePackageHandler($repository, $taxes, $clinic, $clock, new DomainEventPublisher($eventBus));
    }

    private function package(PackagePricingMode $mode, ?int $price): Package
    {
        $package = Package::create(
            id: PackageId::fromString(self::PACKAGE_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            name: PackageName::fromString('Pack chiot'),
            code: PackageCode::fromString('PKG-CHIOT'),
            taxCategory: TaxCategoryCode::fromString(self::TAX),
            pricingMode: $mode,
            fixedPrice: null !== $price ? Money::fromMinorUnits($price, CurrencyCode::fromString('EUR')) : null,
            createdAt: new \DateTimeImmutable('2026-01-01 10:00:00'),
        );
        self::assertNotSame([], $package->pullDomainEvents());

        return $package;
    }
}
