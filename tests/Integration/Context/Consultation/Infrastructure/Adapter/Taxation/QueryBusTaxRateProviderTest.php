<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Consultation\Infrastructure\Adapter\Taxation;

use App\Context\Consultation\Application\Port\TaxRateProviderInterface;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\System\Taxation\Story\TaxonomyBootstrapStory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class QueryBusTaxRateProviderTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID = '01960000-0000-7000-8000-0000000000aa';

    public function testReturnsTheRealRateOfACatalogCategory(): void
    {
        TaxonomyBootstrapStory::load();

        ClinicEntityFactory::createOne([
            'id'               => Uuid::fromString(self::CLINIC_ID),
            'name'             => 'Clinique du Parc',
            'slug'             => 'clinique-tax-rate',
            'countryCode'      => 'FR',
            'jurisdictionCode' => null,
        ]);

        self::assertSame(
            20.0,
            $this->provider()->effectiveRatePercent('veterinary.act.consultation', self::CLINIC_ID),
        );
    }

    public function testReturnsTheReducedRateOfAHospitalizationCategory(): void
    {
        TaxonomyBootstrapStory::load();

        ClinicEntityFactory::createOne([
            'id'               => Uuid::fromString(self::CLINIC_ID),
            'name'             => 'Clinique du Parc',
            'slug'             => 'clinique-tax-rate-reduced',
            'countryCode'      => 'FR',
            'jurisdictionCode' => null,
        ]);

        self::assertSame(
            10.0,
            $this->provider()->effectiveRatePercent('veterinary.act.hospitalization', self::CLINIC_ID),
        );
    }

    public function testReturnsNullForAnUnknownClinic(): void
    {
        TaxonomyBootstrapStory::load();

        self::assertNull(
            $this->provider()->effectiveRatePercent(
                'veterinary.act.consultation',
                '01960000-0000-7000-8000-0000000000bb',
            ),
        );
    }

    private function provider(): TaxRateProviderInterface
    {
        $provider = self::getContainer()->get(TaxRateProviderInterface::class);
        \assert($provider instanceof TaxRateProviderInterface);

        return $provider;
    }
}
