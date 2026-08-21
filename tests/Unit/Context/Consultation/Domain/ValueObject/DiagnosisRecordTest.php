<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Domain\ValueObject;

use App\Context\Consultation\Domain\ValueObject\DiagnosisCertainty;
use App\Context\Consultation\Domain\ValueObject\DiagnosisRecord;
use App\Context\Consultation\Domain\ValueObject\DiagnosisSource;
use App\Context\Consultation\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class DiagnosisRecordTest extends TestCase
{
    private const string USER_ID = '55555555-5555-4555-8555-555555555555';

    public function testCreateTrimsAndKeepsAllFields(): void
    {
        $createdAt = new \DateTimeImmutable('2026-08-20 13:00:00');

        $record = DiagnosisRecord::create(
            '  D-123  ',
            '  Gastro-entérite aiguë  ',
            DiagnosisCertainty::PROBABLE,
            '  Suite à une indiscrétion alimentaire  ',
            true,
            DiagnosisSource::MANUAL,
            $createdAt,
            UserId::fromString(self::USER_ID),
        );

        self::assertNotSame('', $record->getId());
        self::assertSame('D-123', $record->getCode());
        self::assertSame('Gastro-entérite aiguë', $record->getLabel());
        self::assertSame(DiagnosisCertainty::PROBABLE, $record->getCertainty());
        self::assertSame('Suite à une indiscrétion alimentaire', $record->getNote());
        self::assertTrue($record->isPrimary());
        self::assertSame(DiagnosisSource::MANUAL, $record->getSource());
        self::assertSame($createdAt, $record->getCreatedAtUtc());
        self::assertSame(self::USER_ID, $record->getCreatedByUserId());
    }

    public function testCreateNormalizesNullCodeAndNote(): void
    {
        $record = DiagnosisRecord::create(
            null,
            'Otite externe',
            DiagnosisCertainty::CERTAIN,
            null,
            false,
            DiagnosisSource::AI_SUGGESTION,
            new \DateTimeImmutable('2026-08-20 13:00:00'),
            UserId::fromString(self::USER_ID),
        );

        self::assertNull($record->getCode());
        self::assertNull($record->getNote());
        self::assertFalse($record->isPrimary());
        self::assertSame(DiagnosisSource::AI_SUGGESTION, $record->getSource());
    }

    public function testCreateNormalizesBlankCodeAndNoteToNull(): void
    {
        $record = DiagnosisRecord::create(
            '   ',
            'Otite externe',
            DiagnosisCertainty::POSSIBLE,
            '   ',
            false,
            DiagnosisSource::MANUAL,
            new \DateTimeImmutable('2026-08-20 13:00:00'),
            UserId::fromString(self::USER_ID),
        );

        self::assertNull($record->getCode());
        self::assertNull($record->getNote());
    }

    public function testCreateAcceptsFieldsAtMaxLength(): void
    {
        $code  = str_repeat('c', 40);
        $label = str_repeat('l', 255);
        $note  = str_repeat('n', 5000);

        $record = DiagnosisRecord::create(
            $code,
            $label,
            DiagnosisCertainty::EXCLUDED,
            $note,
            false,
            DiagnosisSource::MANUAL,
            new \DateTimeImmutable('2026-08-20 13:00:00'),
            UserId::fromString(self::USER_ID),
        );

        self::assertSame($code, $record->getCode());
        self::assertSame($label, $record->getLabel());
        self::assertSame($note, $record->getNote());
    }

    public function testCreateRejectsEmptyLabel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Diagnosis label cannot be empty');

        DiagnosisRecord::create(
            null,
            '   ',
            DiagnosisCertainty::CERTAIN,
            null,
            false,
            DiagnosisSource::MANUAL,
            new \DateTimeImmutable('2026-08-20 13:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateRejectsTooLongLabel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Diagnosis label cannot exceed 255 characters');

        DiagnosisRecord::create(
            null,
            str_repeat('l', 256),
            DiagnosisCertainty::CERTAIN,
            null,
            false,
            DiagnosisSource::MANUAL,
            new \DateTimeImmutable('2026-08-20 13:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateRejectsTooLongCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Diagnosis code cannot exceed 40 characters');

        DiagnosisRecord::create(
            str_repeat('c', 41),
            'Otite externe',
            DiagnosisCertainty::CERTAIN,
            null,
            false,
            DiagnosisSource::MANUAL,
            new \DateTimeImmutable('2026-08-20 13:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateRejectsTooLongNote(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Diagnosis note cannot exceed 5000 characters');

        DiagnosisRecord::create(
            null,
            'Otite externe',
            DiagnosisCertainty::CERTAIN,
            str_repeat('n', 5001),
            false,
            DiagnosisSource::MANUAL,
            new \DateTimeImmutable('2026-08-20 13:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testReconstituteKeepsProvidedValues(): void
    {
        $createdAt = new \DateTimeImmutable('2026-08-20 13:00:00');

        $record = self::reconstituteRecord($createdAt);

        self::assertSame('88888888-8888-4888-8888-888888888888', $record->getId());
        self::assertSame('D-9', $record->getCode());
        self::assertSame('Insuffisance rénale', $record->getLabel());
        self::assertSame(DiagnosisCertainty::PROBABLE, $record->getCertainty());
        self::assertSame('Confirmée par analyse', $record->getNote());
        self::assertTrue($record->isPrimary());
        self::assertSame(DiagnosisSource::AI_SUGGESTION, $record->getSource());
        self::assertSame($createdAt, $record->getCreatedAtUtc());
        self::assertSame(self::USER_ID, $record->getCreatedByUserId());
    }

    public function testWithDetailsReplacesEditableFieldsOnly(): void
    {
        $createdAt = new \DateTimeImmutable('2026-08-20 13:00:00');
        $record    = self::reconstituteRecord($createdAt);

        $updated = $record->withDetails('  D-10  ', '  Cystite  ', DiagnosisCertainty::CERTAIN, '  Revu  ');

        self::assertNotSame($record, $updated);
        self::assertSame('D-10', $updated->getCode());
        self::assertSame('Cystite', $updated->getLabel());
        self::assertSame(DiagnosisCertainty::CERTAIN, $updated->getCertainty());
        self::assertSame('Revu', $updated->getNote());
        self::assertSame($record->getId(), $updated->getId());
        self::assertTrue($updated->isPrimary());
        self::assertSame(DiagnosisSource::AI_SUGGESTION, $updated->getSource());
        self::assertSame($createdAt, $updated->getCreatedAtUtc());
        self::assertSame(self::USER_ID, $updated->getCreatedByUserId());
    }

    public function testWithDetailsNormalizesBlankCodeAndNoteToNull(): void
    {
        $record = self::reconstituteRecord(new \DateTimeImmutable('2026-08-20 13:00:00'));

        $updated = $record->withDetails(null, 'Cystite', DiagnosisCertainty::CERTAIN, null);

        self::assertNull($updated->getCode());
        self::assertNull($updated->getNote());
    }

    public function testWithDetailsRejectsEmptyLabel(): void
    {
        $record = self::reconstituteRecord(new \DateTimeImmutable('2026-08-20 13:00:00'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Diagnosis label cannot be empty');

        $record->withDetails(null, '  ', DiagnosisCertainty::CERTAIN, null);
    }

    public function testWithPrimaryTogglesTheFlagOnly(): void
    {
        $createdAt = new \DateTimeImmutable('2026-08-20 13:00:00');
        $record    = self::reconstituteRecord($createdAt);

        $updated = $record->withPrimary(false);

        self::assertNotSame($record, $updated);
        self::assertFalse($updated->isPrimary());
        self::assertTrue($record->isPrimary());
        self::assertSame($record->getId(), $updated->getId());
        self::assertSame('D-9', $updated->getCode());
        self::assertSame('Insuffisance rénale', $updated->getLabel());
        self::assertSame(DiagnosisCertainty::PROBABLE, $updated->getCertainty());
        self::assertSame('Confirmée par analyse', $updated->getNote());
        self::assertSame(DiagnosisSource::AI_SUGGESTION, $updated->getSource());
        self::assertSame($createdAt, $updated->getCreatedAtUtc());
        self::assertSame(self::USER_ID, $updated->getCreatedByUserId());
    }

    private static function reconstituteRecord(\DateTimeImmutable $createdAt): DiagnosisRecord
    {
        return DiagnosisRecord::reconstitute(
            id: '88888888-8888-4888-8888-888888888888',
            code: 'D-9',
            label: 'Insuffisance rénale',
            certainty: DiagnosisCertainty::PROBABLE,
            note: 'Confirmée par analyse',
            isPrimary: true,
            source: DiagnosisSource::AI_SUGGESTION,
            createdAtUtc: $createdAt,
            createdByUserId: self::USER_ID,
        );
    }
}
