<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Domain\ValueObject;

use App\Context\Consultation\Domain\ValueObject\PlanActionKind;
use App\Context\Consultation\Domain\ValueObject\PlanActionRecord;
use App\Context\Consultation\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class PlanActionRecordTest extends TestCase
{
    private const string USER_ID = '55555555-5555-4555-8555-555555555555';

    public function testCreateKeepsPriceSnapshotAndTrimsText(): void
    {
        $createdAt = new \DateTimeImmutable('2026-08-20 14:00:00');

        $action = PlanActionRecord::create(
            PlanActionKind::PERFORMED_ACT,
            '  Consultation vaccinale  ',
            '  ACT-01  ',
            '  1 cp matin et soir  ',
            7,
            15,
            2.0,
            4500,
            'EUR',
            '  TVA20  ',
            $createdAt,
            UserId::fromString(self::USER_ID),
        );

        self::assertNotSame('', $action->getId());
        self::assertSame(PlanActionKind::PERFORMED_ACT, $action->getKind());
        self::assertSame('Consultation vaccinale', $action->getDescription());
        self::assertSame('ACT-01', $action->getCatalogCode());
        self::assertSame('1 cp matin et soir', $action->getPosology());
        self::assertSame(7, $action->getDurationDays());
        self::assertSame(15, $action->getFollowUpDays());
        self::assertSame(2.0, $action->getQuantity());
        self::assertSame(4500, $action->getUnitPriceMinorUnits());
        self::assertSame('EUR', $action->getCurrency());
        self::assertSame('TVA20', $action->getTaxCategoryCode());
        self::assertSame($createdAt, $action->getCreatedAtUtc());
        self::assertSame(self::USER_ID, $action->getCreatedByUserId());
    }

    public function testCreateWithoutPriceAllowsNullCurrency(): void
    {
        $action = self::createAdvice();

        self::assertNull($action->getCatalogCode());
        self::assertNull($action->getPosology());
        self::assertNull($action->getDurationDays());
        self::assertNull($action->getFollowUpDays());
        self::assertNull($action->getUnitPriceMinorUnits());
        self::assertNull($action->getCurrency());
        self::assertNull($action->getTaxCategoryCode());
    }

    public function testCreateNormalizesBlankShortTextsToNull(): void
    {
        $action = PlanActionRecord::create(
            PlanActionKind::OTHER,
            'Divers',
            '   ',
            '   ',
            null,
            null,
            1.0,
            null,
            null,
            '   ',
            new \DateTimeImmutable('2026-08-20 14:00:00'),
            UserId::fromString(self::USER_ID),
        );

        self::assertNull($action->getCatalogCode());
        self::assertNull($action->getPosology());
        self::assertNull($action->getTaxCategoryCode());
    }

    public function testCreateAcceptsZeroPriceWithCurrency(): void
    {
        $action = PlanActionRecord::create(
            PlanActionKind::PERFORMED_ACT,
            'Acte offert',
            null,
            null,
            null,
            null,
            1.0,
            0,
            'EUR',
            null,
            new \DateTimeImmutable('2026-08-20 14:00:00'),
            UserId::fromString(self::USER_ID),
        );

        self::assertSame(0, $action->getUnitPriceMinorUnits());
        self::assertSame('EUR', $action->getCurrency());
    }

    public function testCreateRejectsEmptyDescription(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Plan action description cannot be empty');

        self::createWithDescription('   ');
    }

    public function testCreateRejectsTooLongDescription(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Plan action description cannot exceed 255 characters');

        self::createWithDescription(str_repeat('d', 256));
    }

    public function testCreateRejectsTooLongCatalogCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Plan action catalog code cannot exceed 40 characters');

        PlanActionRecord::create(
            PlanActionKind::PERFORMED_ACT,
            'Acte',
            str_repeat('c', 41),
            null,
            null,
            null,
            1.0,
            null,
            null,
            null,
            new \DateTimeImmutable('2026-08-20 14:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateRejectsTooLongPosology(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Plan action posology cannot exceed 255 characters');

        PlanActionRecord::create(
            PlanActionKind::MEDICATION_PRESCRIPTION,
            'Médicament',
            null,
            str_repeat('p', 256),
            null,
            null,
            1.0,
            null,
            null,
            null,
            new \DateTimeImmutable('2026-08-20 14:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateRejectsTooLongTaxCategoryCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Plan action tax category code cannot exceed 40 characters');

        PlanActionRecord::create(
            PlanActionKind::PERFORMED_ACT,
            'Acte',
            null,
            null,
            null,
            null,
            1.0,
            null,
            null,
            str_repeat('t', 41),
            new \DateTimeImmutable('2026-08-20 14:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateRejectsNonPositiveDuration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Plan action duration must be at least 1 day');

        PlanActionRecord::create(
            PlanActionKind::MEDICATION_PRESCRIPTION,
            'Médicament',
            null,
            null,
            0,
            null,
            1.0,
            null,
            null,
            null,
            new \DateTimeImmutable('2026-08-20 14:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateRejectsNonPositiveFollowUpDelay(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Plan action follow-up delay must be at least 1 day');

        PlanActionRecord::create(
            PlanActionKind::FOLLOW_UP_APPOINTMENT,
            'Contrôle',
            null,
            null,
            null,
            0,
            1.0,
            null,
            null,
            null,
            new \DateTimeImmutable('2026-08-20 14:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateRejectsNonPositiveQuantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Plan action quantity must be positive');

        PlanActionRecord::create(
            PlanActionKind::PERFORMED_ACT,
            'Acte',
            null,
            null,
            null,
            null,
            0.0,
            null,
            null,
            null,
            new \DateTimeImmutable('2026-08-20 14:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateRejectsNegativePrice(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Plan action price cannot be negative');

        PlanActionRecord::create(
            PlanActionKind::PERFORMED_ACT,
            'Acte',
            null,
            null,
            null,
            null,
            1.0,
            -1,
            'EUR',
            null,
            new \DateTimeImmutable('2026-08-20 14:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateRejectsPriceWithoutCurrency(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Plan action price requires a currency');

        PlanActionRecord::create(
            PlanActionKind::PERFORMED_ACT,
            'Acte',
            null,
            null,
            null,
            null,
            1.0,
            4500,
            null,
            null,
            new \DateTimeImmutable('2026-08-20 14:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateRejectsPriceWithBlankCurrency(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Plan action price requires a currency');

        PlanActionRecord::create(
            PlanActionKind::PERFORMED_ACT,
            'Acte',
            null,
            null,
            null,
            null,
            1.0,
            4500,
            '  ',
            null,
            new \DateTimeImmutable('2026-08-20 14:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testReconstituteKeepsProvidedValues(): void
    {
        $createdAt = new \DateTimeImmutable('2026-08-20 14:00:00');

        $action = self::reconstituteAction($createdAt);

        self::assertSame('99999999-9999-4999-8999-999999999999', $action->getId());
        self::assertSame(PlanActionKind::PERFORMED_ACT, $action->getKind());
        self::assertSame('Détartrage', $action->getDescription());
        self::assertSame('ACT-42', $action->getCatalogCode());
        self::assertSame('1 fois', $action->getPosology());
        self::assertSame(3, $action->getDurationDays());
        self::assertSame(30, $action->getFollowUpDays());
        self::assertSame(1.5, $action->getQuantity());
        self::assertSame(9900, $action->getUnitPriceMinorUnits());
        self::assertSame('EUR', $action->getCurrency());
        self::assertSame('TVA20', $action->getTaxCategoryCode());
        self::assertSame($createdAt, $action->getCreatedAtUtc());
        self::assertSame(self::USER_ID, $action->getCreatedByUserId());
    }

    public function testWithDetailsPreservesPriceSnapshot(): void
    {
        $createdAt = new \DateTimeImmutable('2026-08-20 14:00:00');
        $action    = self::reconstituteAction($createdAt);

        $updated = $action->withDetails('  Détartrage complet  ', '  2 fois  ', 5, 60, 3.0);

        self::assertNotSame($action, $updated);
        self::assertSame('Détartrage complet', $updated->getDescription());
        self::assertSame('2 fois', $updated->getPosology());
        self::assertSame(5, $updated->getDurationDays());
        self::assertSame(60, $updated->getFollowUpDays());
        self::assertSame(3.0, $updated->getQuantity());
        self::assertSame($action->getId(), $updated->getId());
        self::assertSame(PlanActionKind::PERFORMED_ACT, $updated->getKind());
        self::assertSame('ACT-42', $updated->getCatalogCode());
        self::assertSame(9900, $updated->getUnitPriceMinorUnits());
        self::assertSame('EUR', $updated->getCurrency());
        self::assertSame('TVA20', $updated->getTaxCategoryCode());
        self::assertSame($createdAt, $updated->getCreatedAtUtc());
        self::assertSame(self::USER_ID, $updated->getCreatedByUserId());
    }

    public function testWithDetailsNormalizesBlankPosologyToNull(): void
    {
        $action = self::reconstituteAction(new \DateTimeImmutable('2026-08-20 14:00:00'));

        $updated = $action->withDetails('Détartrage', null, null, null, 1.0);

        self::assertNull($updated->getPosology());
        self::assertNull($updated->getDurationDays());
        self::assertNull($updated->getFollowUpDays());
    }

    public function testWithDetailsRejectsNonPositiveDuration(): void
    {
        $action = self::reconstituteAction(new \DateTimeImmutable('2026-08-20 14:00:00'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Plan action duration must be at least 1 day');

        $action->withDetails('Détartrage', null, 0, null, 1.0);
    }

    public function testWithDetailsRejectsNonPositiveFollowUpDelay(): void
    {
        $action = self::reconstituteAction(new \DateTimeImmutable('2026-08-20 14:00:00'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Plan action follow-up delay must be at least 1 day');

        $action->withDetails('Détartrage', null, null, -1, 1.0);
    }

    public function testWithDetailsRejectsNonPositiveQuantity(): void
    {
        $action = self::reconstituteAction(new \DateTimeImmutable('2026-08-20 14:00:00'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Plan action quantity must be positive');

        $action->withDetails('Détartrage', null, null, null, -2.0);
    }

    public function testCreateRejectsQuantityRoundingToZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Plan action quantity must be positive');

        PlanActionRecord::create(
            PlanActionKind::PERFORMED_ACT,
            'Acte',
            null,
            null,
            null,
            null,
            0.001,
            null,
            null,
            null,
            new \DateTimeImmutable('2026-08-20 14:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateRejectsQuantityOverflowingTheColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Plan action quantity cannot exceed 99999.99');

        PlanActionRecord::create(
            PlanActionKind::PERFORMED_ACT,
            'Acte',
            null,
            null,
            null,
            null,
            100000.0,
            null,
            null,
            null,
            new \DateTimeImmutable('2026-08-20 14:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testWithDetailsRejectsQuantityRoundingToZero(): void
    {
        $action = self::reconstituteAction(new \DateTimeImmutable('2026-08-20 14:00:00'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Plan action quantity must be positive');

        $action->withDetails('Détartrage', null, null, null, 0.004);
    }

    public function testWithDetailsRejectsQuantityOverflowingTheColumn(): void
    {
        $action = self::reconstituteAction(new \DateTimeImmutable('2026-08-20 14:00:00'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Plan action quantity cannot exceed 99999.99');

        $action->withDetails('Détartrage', null, null, null, 1000000.0);
    }

    public function testWithDetailsRejectsEmptyDescription(): void
    {
        $action = self::reconstituteAction(new \DateTimeImmutable('2026-08-20 14:00:00'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Plan action description cannot be empty');

        $action->withDetails('   ', null, null, null, 1.0);
    }

    private static function createAdvice(): PlanActionRecord
    {
        return PlanActionRecord::create(
            PlanActionKind::ADVICE,
            'Repos strict pendant 3 jours',
            null,
            null,
            null,
            null,
            1.0,
            null,
            null,
            null,
            new \DateTimeImmutable('2026-08-20 14:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    private static function createWithDescription(string $description): PlanActionRecord
    {
        return PlanActionRecord::create(
            PlanActionKind::OTHER,
            $description,
            null,
            null,
            null,
            null,
            1.0,
            null,
            null,
            null,
            new \DateTimeImmutable('2026-08-20 14:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    private static function reconstituteAction(\DateTimeImmutable $createdAt): PlanActionRecord
    {
        return PlanActionRecord::reconstitute(
            id: '99999999-9999-4999-8999-999999999999',
            kind: PlanActionKind::PERFORMED_ACT,
            description: 'Détartrage',
            catalogCode: 'ACT-42',
            posology: '1 fois',
            durationDays: 3,
            followUpDays: 30,
            quantity: 1.5,
            unitPriceMinorUnits: 9900,
            currency: 'EUR',
            taxCategoryCode: 'TVA20',
            createdAtUtc: $createdAt,
            createdByUserId: self::USER_ID,
        );
    }
}
