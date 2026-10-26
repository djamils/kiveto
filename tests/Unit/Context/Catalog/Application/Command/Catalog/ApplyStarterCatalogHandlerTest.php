<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Command\Catalog;

use App\Context\Catalog\Application\Command\Act\CreateAct\CreateAct;
use App\Context\Catalog\Application\Command\Article\CreateDrugArticle\CreateDrugArticle;
use App\Context\Catalog\Application\Command\Article\CreateNonDrugArticle\CreateNonDrugArticle;
use App\Context\Catalog\Application\Command\Catalog\ApplyStarterCatalog\ApplyStarterCatalog;
use App\Context\Catalog\Application\Command\Catalog\ApplyStarterCatalog\ApplyStarterCatalogHandler;
use App\Context\Catalog\Application\Port\ClinicInfoProviderInterface;
use App\Context\Catalog\Domain\Act\Repository\ActRepositoryInterface;
use App\Context\Catalog\Domain\Article\Repository\ArticleRepositoryInterface;
use App\Context\Catalog\Domain\Pricing\Repository\PriceListRepositoryInterface;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Tests\Unit\Context\Catalog\Application\Command\CatalogCommandFixtures as F;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ApplyStarterCatalogHandlerTest extends TestCase
{
    public function testTheStarterPricesAreEnteredAllTaxesIncluded(): void
    {
        $dispatched = [];
        $commandBus = $this->createStub(CommandBusInterface::class);
        $commandBus->method('dispatch')->willReturnCallback(
            static function (object $command) use (&$dispatched): string {
                $dispatched[] = $command;

                return F::GENERATED_ID;
            },
        );

        $clinic = $this->createStub(ClinicInfoProviderInterface::class);
        $clinic->method('getCountryCode')->willReturn(CountryCode::fromString('FR'));
        $clinic->method('getCurrencyCode')->willReturn(CurrencyCode::fromString('EUR'));

        $handler = new ApplyStarterCatalogHandler(
            $commandBus,
            $clinic,
            $this->createStub(ActRepositoryInterface::class),
            $this->createStub(ArticleRepositoryInterface::class),
            $this->createStub(PriceListRepositoryInterface::class),
            new NullLogger(),
        );

        $handler(new ApplyStarterCatalog(F::CLINIC_ID));

        $priced = array_values(array_filter(
            $dispatched,
            static fn (object $c): bool => $c instanceof CreateAct || $c instanceof CreateDrugArticle || $c instanceof CreateNonDrugArticle,
        ));

        self::assertNotSame([], array_filter($priced, static fn (object $c): bool => $c instanceof CreateAct));
        self::assertNotSame([], array_filter($priced, static fn (object $c): bool => $c instanceof CreateDrugArticle));
        self::assertNotSame([], array_filter($priced, static fn (object $c): bool => $c instanceof CreateNonDrugArticle));

        foreach ($priced as $command) {
            self::assertSame('incl', $command->basePriceMode);
            self::assertSame('EUR', $command->basePriceCurrency);
        }

        // The standard consultation is listed at 60,00 € TTC.
        $consultation = array_values(array_filter(
            $priced,
            static fn (object $c): bool => $c instanceof CreateAct && 'CONS_STD' === $c->code,
        ))[0];
        self::assertSame(6000, $consultation->basePriceMinorUnits);
    }
}
