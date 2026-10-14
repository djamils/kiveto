<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Domain;

use App\Context\Catalog\Domain\Act\Exception\ActNotFoundException;
use App\Context\Catalog\Domain\Act\Exception\ArchivedActCannotBeModifiedException;
use App\Context\Catalog\Domain\Act\Exception\DuplicateActCodeException;
use App\Context\Catalog\Domain\Act\Exception\InvalidActNameException;
use App\Context\Catalog\Domain\Article\Exception\ArchivedArticleCannotBeModifiedException;
use App\Context\Catalog\Domain\Article\Exception\ArticleNotFoundException;
use App\Context\Catalog\Domain\Article\Exception\AuthorizationNotMarketableException;
use App\Context\Catalog\Domain\Article\Exception\DuplicateArticleCodeException;
use App\Context\Catalog\Domain\Article\Exception\DuplicateGtinException;
use App\Context\Catalog\Domain\Article\Exception\InvalidDrugPropertiesException;
use App\Context\Catalog\Domain\Article\Exception\RegulatoryRestoreForbiddenException;
use App\Context\Catalog\Domain\Exception\ClinicCurrencyMismatchException;
use App\Context\Catalog\Domain\Exception\InvalidPriceAdjustmentException;
use App\Context\Catalog\Domain\Exception\StarterCatalogNotAvailableException;
use App\Context\Catalog\Domain\Package\Exception\ArchivedItemInPackageException;
use App\Context\Catalog\Domain\Package\Exception\DuplicatePackageCodeException;
use App\Context\Catalog\Domain\Package\Exception\EmptyPackageException;
use App\Context\Catalog\Domain\Package\Exception\InvalidPackagePricingModeException;
use App\Context\Catalog\Domain\Package\Exception\PackageNotFoundException;
use App\Context\Catalog\Domain\Pricing\Exception\DefaultPriceListAlreadyExistsException;
use App\Context\Catalog\Domain\Pricing\Exception\NoPriceFoundForItemException;
use App\Context\Catalog\Domain\Pricing\Exception\PriceListNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActNotFoundException::class)]
#[CoversClass(ArchivedActCannotBeModifiedException::class)]
#[CoversClass(DuplicateActCodeException::class)]
#[CoversClass(InvalidActNameException::class)]
#[CoversClass(ArchivedArticleCannotBeModifiedException::class)]
#[CoversClass(ArticleNotFoundException::class)]
#[CoversClass(AuthorizationNotMarketableException::class)]
#[CoversClass(DuplicateArticleCodeException::class)]
#[CoversClass(DuplicateGtinException::class)]
#[CoversClass(InvalidDrugPropertiesException::class)]
#[CoversClass(RegulatoryRestoreForbiddenException::class)]
#[CoversClass(ClinicCurrencyMismatchException::class)]
#[CoversClass(InvalidPriceAdjustmentException::class)]
#[CoversClass(StarterCatalogNotAvailableException::class)]
#[CoversClass(ArchivedItemInPackageException::class)]
#[CoversClass(DuplicatePackageCodeException::class)]
#[CoversClass(EmptyPackageException::class)]
#[CoversClass(InvalidPackagePricingModeException::class)]
#[CoversClass(PackageNotFoundException::class)]
#[CoversClass(DefaultPriceListAlreadyExistsException::class)]
#[CoversClass(NoPriceFoundForItemException::class)]
#[CoversClass(PriceListNotFoundException::class)]
final class CatalogDomainExceptionsTest extends TestCase
{
    /** @param \Closure(): \DomainException $build */
    #[DataProvider('provideAnExceptionExplainsWhatWentWrongCases')]
    public function testAnExceptionExplainsWhatWentWrong(\Closure $build, string $expectedMessage): void
    {
        $exception = $build();

        self::assertSame($expectedMessage, $exception->getMessage());
    }

    /** @return iterable<string, array{\Closure(): \DomainException, string}> */
    public static function provideAnExceptionExplainsWhatWentWrongCases(): iterable
    {
        yield 'act not found' => [
            static fn (): \DomainException => new ActNotFoundException('act-1'),
            'Act "act-1" not found.',
        ];
        yield 'archived act' => [
            static fn (): \DomainException => new ArchivedActCannotBeModifiedException('act-1'),
            'Act "act-1" is archived and cannot be modified.',
        ];
        yield 'duplicate act code' => [
            static fn (): \DomainException => new DuplicateActCodeException('CONS'),
            'An act with code "CONS" already exists for this clinic.',
        ];
        yield 'invalid act name' => [
            static fn (): \DomainException => new InvalidActNameException('Act name cannot be empty.'),
            'Act name cannot be empty.',
        ];
        yield 'archived article' => [
            static fn (): \DomainException => new ArchivedArticleCannotBeModifiedException('art-1'),
            'Article "art-1" is archived and cannot be modified.',
        ];
        yield 'article not found' => [
            static fn (): \DomainException => new ArticleNotFoundException('art-1'),
            'Article "art-1" not found.',
        ];
        yield 'authorization not marketable' => [
            static fn (): \DomainException => new AuthorizationNotMarketableException('AMM-1'),
            'Marketing authorization "AMM-1" is not marketable (withdrawn or not found).',
        ];
        yield 'duplicate article code' => [
            static fn (): \DomainException => new DuplicateArticleCodeException('AMOX'),
            'An article with code "AMOX" already exists for this clinic.',
        ];
        yield 'duplicate gtin' => [
            static fn (): \DomainException => new DuplicateGtinException('3760123456789'),
            'An article with GTIN "3760123456789" already exists for this clinic.',
        ];
        yield 'invalid drug properties' => [
            static fn (): \DomainException => new InvalidDrugPropertiesException('missing class'),
            'Invalid drug properties: missing class',
        ];
        yield 'regulatory restore forbidden' => [
            static fn (): \DomainException => new RegulatoryRestoreForbiddenException('art-1', 'AMM-1'),
            'Cannot restore article "art-1": marketing authorization "AMM-1" is withdrawn.',
        ];
        yield 'clinic currency mismatch' => [
            static fn (): \DomainException => new ClinicCurrencyMismatchException('EUR', 'USD'),
            'Currency mismatch: clinic uses "EUR" but received "USD".',
        ];
        yield 'invalid price adjustment' => [
            static fn (): \DomainException => new InvalidPriceAdjustmentException('negative'),
            'Invalid price adjustment: negative',
        ];
        yield 'starter catalog not available' => [
            static fn (): \DomainException => new StarterCatalogNotAvailableException('BE', 'companion'),
            'No starter catalog available for country "BE" and type "companion".',
        ];
        yield 'archived item in package' => [
            static fn (): \DomainException => new ArchivedItemInPackageException('ACT', 'act-1'),
            'Cannot add archived item "ACT/act-1" to a package.',
        ];
        yield 'duplicate package code' => [
            static fn (): \DomainException => new DuplicatePackageCodeException('PKG'),
            'A package with code "PKG" already exists for this clinic.',
        ];
        yield 'empty package' => [
            static fn (): \DomainException => new EmptyPackageException(),
            'A package must have at least one component.',
        ];
        yield 'invalid package pricing mode' => [
            static fn (): \DomainException => new InvalidPackagePricingModeException('no price'),
            'Invalid package pricing mode: no price',
        ];
        yield 'package not found' => [
            static fn (): \DomainException => new PackageNotFoundException('pkg-1'),
            'Package "pkg-1" not found.',
        ];
        yield 'default price list already exists' => [
            static fn (): \DomainException => new DefaultPriceListAlreadyExistsException('clinic-1'),
            'A default price list already exists for clinic "clinic-1".',
        ];
        yield 'no price found' => [
            static fn (): \DomainException => new NoPriceFoundForItemException('ACT', 'act-1'),
            'No price found for item "ACT/act-1".',
        ];
        yield 'price list not found' => [
            static fn (): \DomainException => new PriceListNotFoundException('pl-1'),
            'Price list "pl-1" not found.',
        ];
    }
}
