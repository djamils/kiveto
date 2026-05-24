<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Catalog\Infrastructure\Repository;

use App\Context\Catalog\Domain\Act\Act;
use App\Context\Catalog\Domain\Act\Repository\ActRepositoryInterface;
use App\Context\Catalog\Domain\Act\ValueObject\ActCategory;
use App\Context\Catalog\Domain\Act\ValueObject\ActCode;
use App\Context\Catalog\Domain\Act\ValueObject\ActDuration;
use App\Context\Catalog\Domain\Act\ValueObject\ActId;
use App\Context\Catalog\Domain\Act\ValueObject\ActName;
use App\Context\Catalog\Domain\Act\ValueObject\ActStatus;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Repository\DoctrineActRepository;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineActRepositoryTest extends KernelTestCase
{
    use Factories;

    public function testSaveAndFindById(): void
    {
        $clinicId = Uuid::v7()->toRfc4122();
        $actId    = Uuid::v7()->toRfc4122();
        $act      = $this->makeAct(actId: $actId, clinicId: $clinicId);
        $repo     = $this->getRepository();

        $repo->save($act);

        $found = $repo->findById(ActId::fromString($actId), ClinicId::fromString($clinicId));

        self::assertNotNull($found);
        self::assertSame($actId, $found->id()->toString());
        self::assertSame($clinicId, $found->clinicId()->toString());
        self::assertSame('Consultation standard', $found->name()->toString());
        self::assertSame('CONS-STD', $found->code()->toString());
        self::assertSame(ActCategory::CONSULTATION, $found->category());
        self::assertSame(5000, $found->basePrice()->minorUnits());
        self::assertSame('EUR', $found->basePrice()->currency()->toString());
        self::assertSame(20, $found->estimatedDuration()->toMinutes());
        self::assertFalse($found->requiresAnesthesia());
        self::assertSame(ActStatus::ACTIVE, $found->status());
    }

    public function testFindByCodeReturnNullForOtherTenant(): void
    {
        $clinicId      = Uuid::v7()->toRfc4122();
        $otherClinicId = Uuid::v7()->toRfc4122();
        $act           = $this->makeAct(clinicId: $clinicId, code: 'CROSS-TEN');
        $repo          = $this->getRepository();

        $repo->save($act);

        $found = $repo->findByCode(ActCode::fromString('CROSS-TEN'), ClinicId::fromString($otherClinicId));

        self::assertNull($found);
    }

    public function testExistsByCode(): void
    {
        $clinicId = Uuid::v7()->toRfc4122();
        $act      = $this->makeAct(clinicId: $clinicId, code: 'EXISTS-CD');
        $repo     = $this->getRepository();

        $repo->save($act);

        self::assertTrue($repo->existsByCode(ActCode::fromString('EXISTS-CD'), ClinicId::fromString($clinicId)));
        self::assertFalse($repo->existsByCode(ActCode::fromString('NO-EXIST'), ClinicId::fromString($clinicId)));
    }

    private function getRepository(): DoctrineActRepository
    {
        $repo = static::getContainer()->get(ActRepositoryInterface::class);
        \assert($repo instanceof DoctrineActRepository);

        return $repo;
    }

    private function makeAct(
        string $actId = '01950000-0000-7000-0000-000000000001',
        string $clinicId = '01950000-0000-7000-0000-000000000002',
        string $code = 'CONS-STD',
    ): Act {
        $now = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');

        return Act::create(
            id: ActId::fromString($actId),
            clinicId: ClinicId::fromString($clinicId),
            name: ActName::fromString('Consultation standard'),
            code: ActCode::fromString($code),
            description: null,
            category: ActCategory::CONSULTATION,
            taxCategory: TaxCategoryCode::fromString('veterinary.act.consultation'),
            basePrice: Money::fromMinorUnits(5000, CurrencyCode::fromString('EUR')),
            estimatedDuration: ActDuration::ofMinutes(20),
            requiresAnesthesia: false,
            createdAt: $now,
        );
    }
}
