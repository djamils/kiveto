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
            [0,  MarketingAuthorizationStatus::UNDER_REVIEW],
            [1,  MarketingAuthorizationStatus::ACTIVE],
            [2,  MarketingAuthorizationStatus::REFUSED],
            [3,  MarketingAuthorizationStatus::EXCEPTIONAL_CIRCUMSTANCES],
            [4,  MarketingAuthorizationStatus::UNLIMITED],
            [5,  MarketingAuthorizationStatus::ABANDONED],
            [6,  MarketingAuthorizationStatus::WITHDRAWN_WITH_DEROGATION],
            [7,  MarketingAuthorizationStatus::LAPSED],
            [8,  MarketingAuthorizationStatus::LAPSED],
            [9,  MarketingAuthorizationStatus::LAPSED],
            [10, MarketingAuthorizationStatus::WITHDRAWN],
            [11, MarketingAuthorizationStatus::SUSPENDED],
            [13, MarketingAuthorizationStatus::UNDER_REVIEW],
            [14, MarketingAuthorizationStatus::UNDER_REVIEW],
            [21, MarketingAuthorizationStatus::ACTIVE],
            [22, MarketingAuthorizationStatus::ABANDONED],
            [23, MarketingAuthorizationStatus::UNDER_REVIEW],
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
            [1,     AdministrationRoute::AURICULAR],
            [2,     AdministrationRoute::CUTANEOUS],
            [3,     AdministrationRoute::IN_OVO],
            [4,     AdministrationRoute::INTRA_ARTICULAR],
            [5,     AdministrationRoute::INTRACARDIAC],
            [6,     AdministrationRoute::INTRADERMAL],
            [8,     AdministrationRoute::INTRAMAMMARY],
            [9,     AdministrationRoute::INTRAMUSCULAR],
            [10,    AdministrationRoute::INTRANASAL],
            [11,    AdministrationRoute::INTRAPERITONEAL],
            [12,    AdministrationRoute::INHALATION],
            [13,    AdministrationRoute::OTHER],
            [14,    AdministrationRoute::INTRARUMINAL],
            [15,    AdministrationRoute::INTRAUTERINE],
            [16,    AdministrationRoute::INTRAVENOUS],
            [17,    AdministrationRoute::OPHTHALMIC],
            [18,    AdministrationRoute::OTHER],
            [19,    AdministrationRoute::ORAL],
            [20,    AdministrationRoute::PERINEURAL],
            [21,    AdministrationRoute::OTHER],
            [22,    AdministrationRoute::PERINEURAL],
            [23,    AdministrationRoute::OPHTHALMIC],
            [24,    AdministrationRoute::SUBCUTANEOUS],
            [25,    AdministrationRoute::OROMUCOSAL],
            [26,    AdministrationRoute::OTHER],
            [27,    AdministrationRoute::TRANSDERMAL],
            [28,    AdministrationRoute::OTHER],
            [29,    AdministrationRoute::TOPICAL],
            [30,    AdministrationRoute::INTRAVAGINAL],
            [537,   AdministrationRoute::OTHER],
            [1239,  AdministrationRoute::INHALATION],
            [1349,  AdministrationRoute::EPIDURAL],
            [1980,  AdministrationRoute::INTRATUMORAL],
            [4109,  AdministrationRoute::INTRANASAL],
            [4110,  AdministrationRoute::OPHTHALMIC],
            [4112,  AdministrationRoute::INTRAVENOUS],
            [5871,  AdministrationRoute::OTHER],
            [5872,  AdministrationRoute::OTHER],
            [6703,  AdministrationRoute::OTHER],
            [7088,  AdministrationRoute::OTHER],
            [7455,  AdministrationRoute::INTRATUMORAL],
            [9073,  AdministrationRoute::INTRA_ARTICULAR],
            [9379,  AdministrationRoute::ENDOSINUSAL],
            [9638,  AdministrationRoute::INHALATION],
            [9704,  AdministrationRoute::ORAL],
            [9705,  AdministrationRoute::ORAL],
            [9949,  AdministrationRoute::ORAL],
            [10432, AdministrationRoute::INTRAMAMMARY],
        ];
    }

    public function testMapAdministrationRouteThrowsOnUnknown(): void
    {
        $this->expectException(UnknownAnmvCodeException::class);
        $this->mapper->mapAdministrationRoute(9999);
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
        // Code 22 = Chien (dog) in the real ANMV dict
        $species = $this->mapper->mapTargetSpecies(22);

        self::assertSame('chien', $species->toString());
    }
}
