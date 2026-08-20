<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Catalog\Application\Port\CatalogSearchRepositoryInterface;
use App\Context\Catalog\Application\Query\Catalog\SearchCatalogItems\CatalogSearchResult;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Fixtures\Context\Catalog\Factory\ActEntityFactory;
use App\Fixtures\Context\Catalog\Factory\ArticleEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineCatalogSearchRepositoryTest extends KernelTestCase
{
    use Factories;

    public function testSearchReturnsPricingTaxAndPrescriptionFlags(): void
    {
        $clinicId = Uuid::v7()->toRfc4122();

        ActEntityFactory::new()->forClinic($clinicId)->create([
            'name'                => 'Zzsearch consultation',
            'code'                => 'ZZS-ACT',
            'basePriceMinorUnits' => 5000,
            'basePriceCurrency'   => 'EUR',
            'taxCategoryCode'     => 'veterinary.act.consultation',
        ]);

        ArticleEntityFactory::new()->drug()->forClinic($clinicId)->create([
            'name'                => 'Zzsearch amoxicilline',
            'code'                => 'ZZS-RX',
            'basePriceMinorUnits' => 1250,
            'basePriceCurrency'   => 'EUR',
            'taxCategoryCode'     => 'veterinary.drug.prescription',
        ]);

        ArticleEntityFactory::new()->forClinic($clinicId)->create([
            'name'                => 'Zzsearch compresses',
            'code'                => 'ZZS-CONS',
            'basePriceMinorUnits' => 350,
            'basePriceCurrency'   => 'EUR',
            'taxCategoryCode'     => 'veterinary.consumable.standard',
        ]);

        $results = $this->getRepository()->search('Zzsearch', ClinicId::fromString($clinicId), 20);

        self::assertCount(3, $results);

        $byCode = $this->indexByCode($results);

        self::assertSame('ACT', $byCode['ZZS-ACT']->type);
        self::assertSame('Zzsearch consultation', $byCode['ZZS-ACT']->name);
        self::assertSame('ACTIVE', $byCode['ZZS-ACT']->status);
        self::assertSame(5000, $byCode['ZZS-ACT']->basePriceMinorUnits);
        self::assertSame('EUR', $byCode['ZZS-ACT']->basePriceCurrency);
        self::assertSame('veterinary.act.consultation', $byCode['ZZS-ACT']->taxCategoryCode);
        self::assertFalse($byCode['ZZS-ACT']->requiresPrescription);

        self::assertSame('ARTICLE', $byCode['ZZS-RX']->type);
        self::assertSame(1250, $byCode['ZZS-RX']->basePriceMinorUnits);
        self::assertSame('veterinary.drug.prescription', $byCode['ZZS-RX']->taxCategoryCode);
        self::assertTrue($byCode['ZZS-RX']->requiresPrescription);

        self::assertSame('ARTICLE', $byCode['ZZS-CONS']->type);
        self::assertSame(350, $byCode['ZZS-CONS']->basePriceMinorUnits);
        self::assertSame('veterinary.consumable.standard', $byCode['ZZS-CONS']->taxCategoryCode);
        self::assertFalse($byCode['ZZS-CONS']->requiresPrescription);
    }

    public function testSearchReturnsRfc4122Identifiers(): void
    {
        $clinicId = Uuid::v7()->toRfc4122();
        $actId    = Uuid::v7();

        ActEntityFactory::new()->forClinic($clinicId)->create([
            'id'   => $actId,
            'name' => 'Zzident vaccination',
            'code' => 'ZZI-ACT',
        ]);

        $results = $this->getRepository()->search('Zzident', ClinicId::fromString($clinicId), 20);

        self::assertCount(1, $results);
        self::assertSame($actId->toRfc4122(), $results[0]->id);
    }

    public function testSearchExcludesItemsOfAnotherClinic(): void
    {
        $clinicId      = Uuid::v7()->toRfc4122();
        $otherClinicId = Uuid::v7()->toRfc4122();

        ActEntityFactory::new()->forClinic($clinicId)->create([
            'name' => 'Zztenant consultation',
            'code' => 'ZZT-ACT-MINE',
        ]);

        ActEntityFactory::new()->forClinic($otherClinicId)->create([
            'name' => 'Zztenant consultation',
            'code' => 'ZZT-ACT-OTHER',
        ]);

        ArticleEntityFactory::new()->drug()->forClinic($otherClinicId)->create([
            'name' => 'Zztenant amoxicilline',
            'code' => 'ZZT-ART-OTHER',
        ]);

        $results = $this->getRepository()->search('Zztenant', ClinicId::fromString($clinicId), 20);

        self::assertCount(1, $results);
        self::assertSame('ZZT-ACT-MINE', $results[0]->code);
    }

    public function testSearchHonoursTheLimit(): void
    {
        $clinicId = Uuid::v7()->toRfc4122();

        ActEntityFactory::new()->forClinic($clinicId)->many(3)->create(static fn (int $i) => [
            'name' => 'Zzlimit acte ' . $i,
            'code' => 'ZZL-ACT-' . $i,
        ]);

        ArticleEntityFactory::new()->forClinic($clinicId)->many(3)->create(static fn (int $i) => [
            'name' => 'Zzlimit article ' . $i,
            'code' => 'ZZL-ART-' . $i,
        ]);

        $repository = $this->getRepository();
        $clinic     = ClinicId::fromString($clinicId);

        self::assertCount(6, $repository->search('Zzlimit', $clinic, 20));
        self::assertCount(4, $repository->search('Zzlimit', $clinic, 4));
        self::assertCount(1, $repository->search('Zzlimit', $clinic, 1));
    }

    public function testSearchReturnsEmptyListWhenNothingMatches(): void
    {
        $clinicId = Uuid::v7()->toRfc4122();

        ActEntityFactory::new()->forClinic($clinicId)->create([
            'name' => 'Zznomatch consultation',
            'code' => 'ZZN-ACT',
        ]);

        $results = $this->getRepository()->search('Zzabsent', ClinicId::fromString($clinicId), 20);

        self::assertSame([], $results);
    }

    private function getRepository(): CatalogSearchRepositoryInterface
    {
        $repository = static::getContainer()->get(CatalogSearchRepositoryInterface::class);
        \assert($repository instanceof CatalogSearchRepositoryInterface);

        return $repository;
    }

    /**
     * @param list<CatalogSearchResult> $results
     *
     * @return array<string, CatalogSearchResult>
     */
    private function indexByCode(array $results): array
    {
        $byCode = [];

        foreach ($results as $result) {
            $byCode[$result->code] = $result;
        }

        return $byCode;
    }
}
