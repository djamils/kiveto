<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Domain\Act;

use App\Context\Catalog\Domain\Act\Act;
use App\Context\Catalog\Domain\Act\Event\ActTaxCategoryChanged;
use App\Context\Catalog\Domain\Act\Exception\ArchivedActCannotBeModifiedException;
use App\Context\Catalog\Domain\Act\ValueObject\ActCategory;
use App\Context\Catalog\Domain\Act\ValueObject\ActStatus;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\Tests\Unit\Context\Catalog\Application\Command\CatalogCommandFixtures as F;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Act::class)]
final class ActModificationTest extends TestCase
{
    public function testAReconstitutedActExposesItsState(): void
    {
        $act = F::act();

        self::assertSame(F::ACT_ID, $act->id()->toString());
        self::assertSame(F::CLINIC_ID, $act->clinicId()->toString());
        self::assertSame('Consultation standard', $act->name()->toString());
        self::assertSame('CONS-STD', $act->code()->toString());
        self::assertSame(ActCategory::CONSULTATION, $act->category());
        self::assertSame('veterinary.act.consultation', $act->taxCategory()->toString());
        self::assertSame(5000, $act->basePrice()->minorUnits());
        self::assertSame(20, $act->estimatedDuration()->toMinutes());
        self::assertFalse($act->requiresAnesthesia());
        self::assertSame(ActStatus::ACTIVE, $act->status());
        self::assertSame('2026-01-01 10:00:00', $act->createdAt()->format('Y-m-d H:i:s'));
        self::assertSame('2026-01-01 10:00:00', $act->updatedAt()->format('Y-m-d H:i:s'));
    }

    public function testChangingTheTaxCategoryIsRecorded(): void
    {
        $act = F::act();

        $act->changeTaxCategory(TaxCategoryCode::fromString('veterinary.act.surgery'), F::now());

        self::assertSame('veterinary.act.surgery', $act->taxCategory()->toString());
        self::assertSame(F::now(), $act->updatedAt());

        $events = $act->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ActTaxCategoryChanged::class, $events[0]);
    }

    public function testAnArchivedActKeepsItsTaxCategory(): void
    {
        $act = F::act(ActStatus::ARCHIVED);

        $this->expectException(ArchivedActCannotBeModifiedException::class);

        $act->changeTaxCategory(TaxCategoryCode::fromString('veterinary.act.surgery'), F::now());
    }
}
