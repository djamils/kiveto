<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Taxation\Infrastructure\Persistence;

use App\Fixtures\System\Taxation\Factory\TaxCategoryEntityFactory;
use App\Shared\Domain\ValueObject\CountryCode;
use App\System\Taxation\Domain\Entity\MentionTemplate;
use App\System\Taxation\Domain\Entity\TaxRate;
use App\System\Taxation\Domain\TaxCategory;
use App\System\Taxation\Domain\TaxRegime;
use App\System\Taxation\Domain\ValueObject\CustomerTaxStatus;
use App\System\Taxation\Domain\ValueObject\MentionTemplateCondition;
use App\System\Taxation\Domain\ValueObject\MentionTemplateId;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\System\Taxation\Domain\ValueObject\TaxCategoryNature;
use App\System\Taxation\Domain\ValueObject\TaxRateCondition;
use App\System\Taxation\Domain\ValueObject\TaxRateId;
use App\System\Taxation\Domain\ValueObject\TaxRateValue;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use App\System\Taxation\Domain\ValueObject\ValidityPeriod;
use App\System\Taxation\Infrastructure\Persistence\Doctrine\Repository\DoctrineMentionTemplateRepository;
use App\System\Taxation\Infrastructure\Persistence\Doctrine\Repository\DoctrineTaxCategoryRepository;
use App\System\Taxation\Infrastructure\Persistence\Doctrine\Repository\DoctrineTaxRegimeRepository;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Support\Database\QueryCounter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * The reference data every taxed line resolves (categories, regimes and
 * their rates, mention templates) is read once for the request: again after
 * a write, and after the kernel reset.
 */
final class TaxationReferenceMemoTest extends KernelTestCase
{
    use Factories;

    public function testTheCategoriesAreReadOnceForAllCodes(): void
    {
        $this->category('veterinary.act.consultation');
        $this->category('veterinary.medicine.oral');
        $categories = $this->service(DoctrineTaxCategoryRepository::class);

        QueryCounter::reset();
        self::assertSame('veterinary.act.consultation', $categories->findByCode(TaxCategoryCode::fromString('veterinary.act.consultation'))?->code()->toString());
        self::assertSame('veterinary.medicine.oral', $categories->findByCode(TaxCategoryCode::fromString('veterinary.medicine.oral'))?->code()->toString());
        self::assertNull($categories->findByCode(TaxCategoryCode::fromString('veterinary.act.unknown')));
        self::assertSame(1, QueryCounter::count(), 'one read for every code');

        // A save is seen at once.
        $categories->save(TaxCategory::create(TaxCategoryCode::fromString('veterinary.act.unknown'), 'Nouvelle', null, TaxCategoryNature::SERVICES, new FrozenClock(new \DateTimeImmutable())));
        self::assertNotNull($categories->findByCode(TaxCategoryCode::fromString('veterinary.act.unknown')));

        // The next request reads again.
        $categories->reset();
        QueryCounter::reset();
        $categories->findByCode(TaxCategoryCode::fromString('veterinary.act.consultation'));
        self::assertSame(1, QueryCounter::count());
    }

    public function testARegimeIsReadOnceUntilSaved(): void
    {
        $clock   = new FrozenClock(new \DateTimeImmutable('2026-01-15T10:00:00+00:00'));
        $regimes = $this->service(DoctrineTaxRegimeRepository::class);
        $regime  = TaxRegime::create(TaxRegimeId::fromString('FR'), 'Régime TVA France', CountryCode::fromString('FR'), $clock, true);
        $regime->addRate(TaxRate::of(
            TaxRateId::fromString('fr.standard'),
            TaxRateValue::ofBasisPoints(2000),
            ValidityPeriod::openEndedFrom(new \DateTimeImmutable('2014-01-01')),
            TaxRateCondition::of([], [], [], [], null),
        ), $clock);
        $regimes->save($regime);
        $regimes->reset();

        QueryCounter::reset();
        $first = $regimes->findById(TaxRegimeId::fromString('FR'));
        $read  = QueryCounter::count();
        self::assertSame($first, $regimes->findById(TaxRegimeId::fromString('FR')));
        self::assertNull($regimes->findById(TaxRegimeId::fromString('BE')));
        self::assertNull($regimes->findById(TaxRegimeId::fromString('BE')));
        self::assertGreaterThan(0, $read);
        self::assertSame($read + 1, QueryCounter::count(), 'FR read once; BE (unknown) read once');

        // A save forgets what was read; so does the next request.
        $regimes->save(TaxRegime::create(TaxRegimeId::fromString('BE'), 'Régime TVA Belgique', CountryCode::fromString('BE'), $clock, true));
        self::assertSame('Régime TVA Belgique', $regimes->findById(TaxRegimeId::fromString('BE'))?->name());
        self::assertNotSame($first, $regimes->findById(TaxRegimeId::fromString('FR')));

        $kept = $regimes->findById(TaxRegimeId::fromString('FR'));
        $regimes->reset();
        self::assertNotSame($kept, $regimes->findById(TaxRegimeId::fromString('FR')));
    }

    public function testARegimesTemplatesAreReadOnceUntilWritten(): void
    {
        $templates = $this->service(DoctrineMentionTemplateRepository::class);
        $regimeId  = TaxRegimeId::fromString('FR');
        $templates->save($this->template($regimeId, 'INTRACOM_REVERSE_CHARGE'));

        QueryCounter::reset();
        self::assertCount(1, $templates->findActiveByRegime($regimeId));
        self::assertCount(1, $templates->findActiveByRegime($regimeId));
        self::assertSame(1, QueryCounter::count());

        $templates->save($this->template($regimeId, 'EXPORT'));
        self::assertCount(2, $templates->findActiveByRegime($regimeId));

        $templates->deleteByRegime($regimeId);
        self::assertSame([], $templates->findActiveByRegime($regimeId));

        $templates->reset();
        QueryCounter::reset();
        $templates->findActiveByRegime($regimeId);
        self::assertSame(1, QueryCounter::count());
    }

    private function category(string $code): void
    {
        $now = new \DateTimeImmutable();
        TaxCategoryEntityFactory::createOne(['code' => $code, 'displayName' => $code, 'description' => null, 'active' => true, 'createdAt' => $now, 'updatedAt' => $now]);
    }

    private function template(TaxRegimeId $regimeId, string $code): MentionTemplate
    {
        return MentionTemplate::create(
            new MentionTemplateId(Uuid::v4()->toRfc4122()),
            $regimeId,
            $code,
            MentionTemplateCondition::of([], [CustomerTaxStatus::INTRACOM], [], null),
            'Mention ' . $code,
            'fr_FR',
            new FrozenClock(new \DateTimeImmutable('2026-01-15T10:00:00+00:00')),
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $class): object
    {
        $service = static::getContainer()->get($class);
        \assert($service instanceof $class);

        return $service;
    }
}
