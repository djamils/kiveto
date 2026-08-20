<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Domain\ValueObject;

use App\Context\Consultation\Domain\ValueObject\TypedVitalRecord;
use App\Context\Consultation\Domain\ValueObject\UserId;
use App\Context\Consultation\Domain\ValueObject\VitalType;
use PHPUnit\Framework\TestCase;

final class TypedVitalRecordTest extends TestCase
{
    private const string USER_ID = '55555555-5555-4555-8555-555555555555';

    public function testCreateNumericVital(): void
    {
        $recordedAt = new \DateTimeImmutable('2026-08-20 10:15:00');

        $record = TypedVitalRecord::create(
            VitalType::HEART_RATE,
            ' 120 ',
            $recordedAt,
            UserId::fromString(self::USER_ID),
        );

        self::assertNotSame('', $record->getId());
        self::assertSame(VitalType::HEART_RATE, $record->getType());
        self::assertSame('120', $record->getValue());
        self::assertSame($recordedAt, $record->getRecordedAtUtc());
        self::assertSame(self::USER_ID, $record->getRecordedByUserId());
    }

    public function testCreateFreeTextVitalSkipsRangeValidation(): void
    {
        $record = TypedVitalRecord::create(
            VitalType::MUCOUS_MEMBRANES,
            'Roses et humides',
            new \DateTimeImmutable('2026-08-20 10:15:00'),
            UserId::fromString(self::USER_ID),
        );

        self::assertSame('Roses et humides', $record->getValue());
    }

    public function testCreateAcceptsValuesAtBounds(): void
    {
        $recordedAt = new \DateTimeImmutable('2026-08-20 10:15:00');
        $userId     = UserId::fromString(self::USER_ID);

        $low  = TypedVitalRecord::create(VitalType::HEART_RATE, '10', $recordedAt, $userId);
        $high = TypedVitalRecord::create(VitalType::HEART_RATE, '400', $recordedAt, $userId);

        self::assertSame('10', $low->getValue());
        self::assertSame('400', $high->getValue());
    }

    public function testCreateAcceptsValueAtMaxLength(): void
    {
        $value = str_repeat('a', 60);

        $record = TypedVitalRecord::create(
            VitalType::MUCOUS_MEMBRANES,
            $value,
            new \DateTimeImmutable('2026-08-20 10:15:00'),
            UserId::fromString(self::USER_ID),
        );

        self::assertSame($value, $record->getValue());
    }

    public function testCreateRejectsEmptyValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Vital value cannot be empty');

        TypedVitalRecord::create(
            VitalType::HEART_RATE,
            '   ',
            new \DateTimeImmutable('2026-08-20 10:15:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateRejectsTooLongValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Vital value cannot exceed 60 characters');

        TypedVitalRecord::create(
            VitalType::MUCOUS_MEMBRANES,
            str_repeat('a', 61),
            new \DateTimeImmutable('2026-08-20 10:15:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateRejectsNonNumericValueForNumericType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Fréq. cardiaque must be a numeric value');

        TypedVitalRecord::create(
            VitalType::HEART_RATE,
            'rapide',
            new \DateTimeImmutable('2026-08-20 10:15:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateRejectsValueBelowMin(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Fréq. cardiaque must be between 10 and 400');

        TypedVitalRecord::create(
            VitalType::HEART_RATE,
            '9',
            new \DateTimeImmutable('2026-08-20 10:15:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateRejectsValueAboveMax(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Glycémie must be between 0 and 60');

        TypedVitalRecord::create(
            VitalType::GLYCEMIA,
            '61',
            new \DateTimeImmutable('2026-08-20 10:15:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateFormatsFractionalBoundsInErrorMessage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TRC must be between 0 and 10');

        TypedVitalRecord::create(
            VitalType::CAPILLARY_REFILL_TIME,
            '10.5',
            new \DateTimeImmutable('2026-08-20 10:15:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testReconstituteKeepsProvidedValues(): void
    {
        $recordedAt = new \DateTimeImmutable('2026-08-20 10:15:00');

        $record = TypedVitalRecord::reconstitute(
            id: '66666666-6666-4666-8666-666666666666',
            type: VitalType::BLOOD_PRESSURE,
            value: '130/85',
            recordedAtUtc: $recordedAt,
            recordedByUserId: self::USER_ID,
        );

        self::assertSame('66666666-6666-4666-8666-666666666666', $record->getId());
        self::assertSame(VitalType::BLOOD_PRESSURE, $record->getType());
        self::assertSame('130/85', $record->getValue());
        self::assertSame($recordedAt, $record->getRecordedAtUtc());
        self::assertSame(self::USER_ID, $record->getRecordedByUserId());
    }
}
