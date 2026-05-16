<?php

declare(strict_types=1);

namespace Tests\Unit\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France;

use App\System\PharmaceuticalRegistry\Domain\Exception\UnknownAnmvCodeException;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\AdministrationRoute;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\MarketingAuthorizationStatus;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ProductNature;
use App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France\AnmvCodeMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AnmvCodeMapperTest extends TestCase
{
    private AnmvCodeMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new AnmvCodeMapper();
    }

    #[DataProvider('provideMapAuthorizationStatusCases')]
    public function testMapAuthorizationStatus(int $code, MarketingAuthorizationStatus $expected): void
    {
        self::assertSame($expected, $this->mapper->mapAuthorizationStatus($code));
    }

    /** @return array<array{int, MarketingAuthorizationStatus}> */
    public static function provideMapAuthorizationStatusCases(): iterable
    {
        return [
            [1, MarketingAuthorizationStatus::UNDER_REVIEW],
            [2, MarketingAuthorizationStatus::ACTIVE],
            [3, MarketingAuthorizationStatus::EXCEPTIONAL_CIRCUMSTANCES],
            [5, MarketingAuthorizationStatus::WITHDRAWN],
            [7, MarketingAuthorizationStatus::SUSPENDED],
            [8, MarketingAuthorizationStatus::REFUSED],
            [9, MarketingAuthorizationStatus::ABANDONED],
            [10, MarketingAuthorizationStatus::LAPSED],
        ];
    }

    public function testMapAuthorizationStatusThrowsOnUnknown(): void
    {
        $this->expectException(UnknownAnmvCodeException::class);
        $this->expectExceptionMessage('9999');
        $this->mapper->mapAuthorizationStatus(9999);
    }

    #[DataProvider('provideMapProductNatureCases')]
    public function testMapProductNature(int $code, ProductNature $expected): void
    {
        self::assertSame($expected, $this->mapper->mapProductNature($code));
    }

    /** @return array<array{int, ProductNature}> */
    public static function provideMapProductNatureCases(): iterable
    {
        return [
            [1, ProductNature::CHEMICAL],
            [2, ProductNature::IMMUNOLOGICAL],
            [3, ProductNature::HOMEOPATHIC],
        ];
    }

    #[DataProvider('provideMapAdministrationRouteCases')]
    public function testMapAdministrationRoute(int $code, AdministrationRoute $expected): void
    {
        self::assertSame($expected, $this->mapper->mapAdministrationRoute($code));
    }

    /** @return array<array{int, AdministrationRoute}> */
    public static function provideMapAdministrationRouteCases(): iterable
    {
        return [
            [1, AdministrationRoute::AURICULAR],
            [14, AdministrationRoute::ORAL],
            [18, AdministrationRoute::SUBCUTANEOUS],
            [49, AdministrationRoute::OTHER],
        ];
    }

    public function testMapAdministrationRouteThrowsOnUnknown(): void
    {
        $this->expectException(UnknownAnmvCodeException::class);
        $this->mapper->mapAdministrationRoute(9999);
    }

    public function testMapFoodProductionPurposeReturnsNullForCode10(): void
    {
        self::assertNull($this->mapper->mapFoodProductionPurpose(10));
    }

    public function testMapFoodProductionPurposeReturnsNullForNull(): void
    {
        self::assertNull($this->mapper->mapFoodProductionPurpose(null));
    }

    public function testGetMappedCountsAreAccurate(): void
    {
        self::assertSame(49, $this->mapper->getMappedRouteCount());
        self::assertSame(17, $this->mapper->getMappedStatusCount());
        self::assertSame(22, $this->mapper->getMappedPrescriptionCount());
    }

    public function testMapTargetSpeciesThrowsOnUnknownCode(): void
    {
        $this->expectException(UnknownAnmvCodeException::class);
        $this->mapper->mapTargetSpecies(99999);
    }

    public function testMapTargetSpeciesReturnsKnownSlug(): void
    {
        $species = $this->mapper->mapTargetSpecies(7);

        self::assertSame('dog', $species->toString());
    }
}
