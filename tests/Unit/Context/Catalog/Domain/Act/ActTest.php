<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Domain\Act;

use App\Context\Catalog\Domain\Act\Act;
use App\Context\Catalog\Domain\Act\Event\ActArchived;
use App\Context\Catalog\Domain\Act\Event\ActBasePriceChanged;
use App\Context\Catalog\Domain\Act\Event\ActCreated;
use App\Context\Catalog\Domain\Act\Event\ActRenamed;
use App\Context\Catalog\Domain\Act\Event\ActRestored;
use App\Context\Catalog\Domain\Act\Exception\ArchivedActCannotBeModifiedException;
use App\Context\Catalog\Domain\Act\ValueObject\ActCategory;
use App\Context\Catalog\Domain\Act\ValueObject\ActCode;
use App\Context\Catalog\Domain\Act\ValueObject\ActDuration;
use App\Context\Catalog\Domain\Act\ValueObject\ActId;
use App\Context\Catalog\Domain\Act\ValueObject\ActName;
use App\Context\Catalog\Domain\Act\ValueObject\ActStatus;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use PHPUnit\Framework\TestCase;

final class ActTest extends TestCase
{
    public function testCreateRecordsEvent(): void
    {
        $now = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $act = Act::create(
            id: ActId::fromString('01950000-0000-7000-0000-000000000001'),
            clinicId: ClinicId::fromString('01950000-0000-7000-0000-000000000002'),
            name: ActName::fromString('Consultation'),
            code: ActCode::fromString('CONS'),
            description: null,
            category: ActCategory::CONSULTATION,
            taxCategory: TaxCategoryCode::fromString('veterinary.act.consultation'),
            basePrice: Money::fromMinorUnits(5000, CurrencyCode::fromString('EUR')),
            estimatedDuration: ActDuration::ofMinutes(20),
            requiresAnesthesia: false,
            createdAt: $now,
        );

        $events = $_ = $act->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(ActCreated::class, $events[0]);
        self::assertSame('01950000-0000-7000-0000-000000000001', $events[0]->actId);
        self::assertSame('CONS', $events[0]->code);
        self::assertSame(5000, $events[0]->basePriceMinorUnits);
    }

    public function testReconstituteSilent(): void
    {
        $now = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $act = Act::reconstitute(
            id: ActId::fromString('01950000-0000-7000-0000-000000000001'),
            clinicId: ClinicId::fromString('01950000-0000-7000-0000-000000000002'),
            name: ActName::fromString('Consultation'),
            code: ActCode::fromString('CONS'),
            description: null,
            category: ActCategory::CONSULTATION,
            taxCategory: TaxCategoryCode::fromString('veterinary.act.consultation'),
            basePrice: Money::fromMinorUnits(5000, CurrencyCode::fromString('EUR')),
            estimatedDuration: ActDuration::ofMinutes(20),
            requiresAnesthesia: false,
            status: ActStatus::ACTIVE,
            createdAt: $now,
            updatedAt: $now,
        );

        self::assertSame([], $act->pullDomainEvents());
    }

    public function testArchiveIdempotent(): void
    {
        $now = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $act = $this->makeAct();

        $act->archive($now);
        $act->archive($now);

        $events = $_ = $act->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(ActArchived::class, $events[0]);
    }

    public function testRestoreIdempotent(): void
    {
        $now = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $act = $this->makeAct();

        $act->restore($now);
        $act->restore($now);

        self::assertSame([], $act->pullDomainEvents());
    }

    public function testRenameThrowsWhenArchived(): void
    {
        $now = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $act = $this->makeAct(ActStatus::ARCHIVED);

        $this->expectException(ArchivedActCannotBeModifiedException::class);

        $act->rename(ActName::fromString('New Name'), $now);
    }

    public function testChangeBasePriceThrowsWhenArchived(): void
    {
        $now = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $act = $this->makeAct(ActStatus::ARCHIVED);

        $this->expectException(ArchivedActCannotBeModifiedException::class);

        $act->changeBasePrice(Money::fromMinorUnits(1000, CurrencyCode::fromString('EUR')), $now);
    }

    public function testArchiveThenRestoreFullCycle(): void
    {
        $now = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $act = $this->makeAct();

        $act->archive($now);
        self::assertSame(ActStatus::ARCHIVED, $act->status());

        $act->restore($now);
        self::assertSame(ActStatus::ACTIVE, $act->status());

        $events = $_ = $act->pullDomainEvents();

        self::assertCount(2, $events);
        self::assertInstanceOf(ActArchived::class, $events[0]);
        self::assertInstanceOf(ActRestored::class, $events[1]);
    }

    public function testRenameEmitsEvent(): void
    {
        $now = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $act = $this->makeAct();

        $act->rename(ActName::fromString('New Name'), $now);

        $events = $_ = $act->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(ActRenamed::class, $events[0]);
        self::assertSame('New Name', $events[0]->newName);
    }

    public function testChangeBasePriceEmitsEvent(): void
    {
        $now = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $act = $this->makeAct();

        $act->changeBasePrice(Money::fromMinorUnits(9999, CurrencyCode::fromString('EUR')), $now);

        $events = $_ = $act->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(ActBasePriceChanged::class, $events[0]);
        self::assertSame(9999, $events[0]->newPriceMinorUnits);
    }

    private function makeAct(?ActStatus $status = null): Act
    {
        $now = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $act = Act::create(
            id: ActId::fromString('01950000-0000-7000-0000-000000000001'),
            clinicId: ClinicId::fromString('01950000-0000-7000-0000-000000000002'),
            name: ActName::fromString('Consultation standard'),
            code: ActCode::fromString('CONS-STD'),
            description: null,
            category: ActCategory::CONSULTATION,
            taxCategory: TaxCategoryCode::fromString('veterinary.act.consultation'),
            basePrice: Money::fromMinorUnits(5000, CurrencyCode::fromString('EUR')),
            estimatedDuration: ActDuration::ofMinutes(20),
            requiresAnesthesia: false,
            createdAt: $now,
        );
        $_ = $act->pullDomainEvents();

        if (null !== $status && ActStatus::ARCHIVED === $status) {
            $act->archive($now);
            $_ = $act->pullDomainEvents();
        }

        return $act;
    }
}
