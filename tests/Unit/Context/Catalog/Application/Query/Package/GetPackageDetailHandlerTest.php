<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Query\Package;

use App\Context\Catalog\Application\Query\Package\GetPackageDetail\GetPackageDetail;
use App\Context\Catalog\Application\Query\Package\GetPackageDetail\GetPackageDetailHandler;
use App\Context\Catalog\Domain\Package\Repository\PackageRepositoryInterface;
use App\Context\Catalog\Domain\Package\ValueObject\PackagePricingMode;
use App\Tests\Unit\Context\Catalog\Application\Command\CatalogCommandFixtures as F;
use PHPUnit\Framework\TestCase;

final class GetPackageDetailHandlerTest extends TestCase
{
    public function testAFixedPriceIsShownAsEnteredWithItsMode(): void
    {
        $repository = $this->createStub(PackageRepositoryInterface::class);
        $repository->method('findById')->willReturn(F::package(PackagePricingMode::FIXED_PRICE));

        $view = (new GetPackageDetailHandler($repository))(new GetPackageDetail(F::PACKAGE_ID, F::CLINIC_ID));

        self::assertSame(25000, $view->fixedPriceMinorUnits);
        self::assertSame('EUR', $view->fixedPriceCurrency);
        self::assertSame('incl', $view->fixedPriceMode);
    }

    public function testASumOfComponentsPackageHasNoPriceNorMode(): void
    {
        $repository = $this->createStub(PackageRepositoryInterface::class);
        $repository->method('findById')->willReturn(F::package(PackagePricingMode::SUM_OF_COMPONENTS));

        $view = (new GetPackageDetailHandler($repository))(new GetPackageDetail(F::PACKAGE_ID, F::CLINIC_ID));

        self::assertNull($view->fixedPriceMinorUnits);
        self::assertNull($view->fixedPriceMode);
    }
}
