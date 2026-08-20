<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Domain\ValueObject;

use App\Context\Consultation\Domain\ValueObject\BodySystem;
use App\Context\Consultation\Domain\ValueObject\ExamStatus;
use App\Context\Consultation\Domain\ValueObject\ExamSystemRecord;
use App\Context\Consultation\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class ExamSystemRecordTest extends TestCase
{
    private const string USER_ID = '55555555-5555-4555-8555-555555555555';

    public function testCreateTrimsNotesAndStructuredData(): void
    {
        $recordedAt = new \DateTimeImmutable('2026-08-20 11:00:00');

        $record = ExamSystemRecord::create(
            BodySystem::CARDIOVASCULAR,
            ExamStatus::ANOMALY,
            '  Souffle systolique  ',
            ['fc' => ' 120 ', 'rhythm' => 'régulier', 'murmur' => '  '],
            $recordedAt,
            UserId::fromString(self::USER_ID),
        );

        self::assertNotSame('', $record->getId());
        self::assertSame(BodySystem::CARDIOVASCULAR, $record->getSystem());
        self::assertSame(ExamStatus::ANOMALY, $record->getStatus());
        self::assertSame('Souffle systolique', $record->getNotes());
        self::assertSame(['fc' => '120', 'rhythm' => 'régulier'], $record->getStructuredData());
        self::assertSame($recordedAt, $record->getRecordedAtUtc());
        self::assertSame(self::USER_ID, $record->getRecordedByUserId());
    }

    public function testCreateNormalizesNullNotesToNull(): void
    {
        $record = ExamSystemRecord::create(
            BodySystem::RESPIRATORY,
            ExamStatus::NORMAL,
            null,
            [],
            new \DateTimeImmutable('2026-08-20 11:00:00'),
            UserId::fromString(self::USER_ID),
        );

        self::assertNull($record->getNotes());
        self::assertSame([], $record->getStructuredData());
    }

    public function testCreateNormalizesBlankNotesToNull(): void
    {
        $record = ExamSystemRecord::create(
            BodySystem::RESPIRATORY,
            ExamStatus::NORMAL,
            '   ',
            [],
            new \DateTimeImmutable('2026-08-20 11:00:00'),
            UserId::fromString(self::USER_ID),
        );

        self::assertNull($record->getNotes());
    }

    public function testCreateAcceptsNotesAtMaxLength(): void
    {
        $notes = str_repeat('a', 5000);

        $record = ExamSystemRecord::create(
            BodySystem::SKIN,
            ExamStatus::ANOMALY,
            $notes,
            [],
            new \DateTimeImmutable('2026-08-20 11:00:00'),
            UserId::fromString(self::USER_ID),
        );

        self::assertSame($notes, $record->getNotes());
    }

    public function testCreateRejectsTooLongNotes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Exam notes cannot exceed 5000 characters');

        ExamSystemRecord::create(
            BodySystem::SKIN,
            ExamStatus::ANOMALY,
            str_repeat('a', 5001),
            [],
            new \DateTimeImmutable('2026-08-20 11:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateAcceptsStructuredFieldAtMaxLength(): void
    {
        $value = str_repeat('a', 255);

        $record = ExamSystemRecord::create(
            BodySystem::LOCOMOTOR,
            ExamStatus::ANOMALY,
            null,
            ['region' => $value],
            new \DateTimeImmutable('2026-08-20 11:00:00'),
            UserId::fromString(self::USER_ID),
        );

        self::assertSame(['region' => $value], $record->getStructuredData());
    }

    public function testCreateRejectsTooLongStructuredField(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Exam structured field "region" cannot exceed 255 characters');

        ExamSystemRecord::create(
            BodySystem::LOCOMOTOR,
            ExamStatus::ANOMALY,
            null,
            ['region' => str_repeat('a', 256)],
            new \DateTimeImmutable('2026-08-20 11:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testReconstituteKeepsProvidedValues(): void
    {
        $recordedAt = new \DateTimeImmutable('2026-08-20 11:00:00');

        $record = ExamSystemRecord::reconstitute(
            id: '77777777-7777-4777-8777-777777777777',
            system: BodySystem::DENTAL,
            status: ExamStatus::UNTESTED,
            notes: 'Non coopératif',
            structuredData: ['foo' => 'bar'],
            recordedAtUtc: $recordedAt,
            recordedByUserId: self::USER_ID,
        );

        self::assertSame('77777777-7777-4777-8777-777777777777', $record->getId());
        self::assertSame(BodySystem::DENTAL, $record->getSystem());
        self::assertSame(ExamStatus::UNTESTED, $record->getStatus());
        self::assertSame('Non coopératif', $record->getNotes());
        self::assertSame(['foo' => 'bar'], $record->getStructuredData());
        self::assertSame($recordedAt, $record->getRecordedAtUtc());
        self::assertSame(self::USER_ID, $record->getRecordedByUserId());
    }

    public function testWithStatusReplacesStatusAndTimestamp(): void
    {
        $record = ExamSystemRecord::reconstitute(
            id: '77777777-7777-4777-8777-777777777777',
            system: BodySystem::DENTAL,
            status: ExamStatus::UNTESTED,
            notes: 'Non coopératif',
            structuredData: ['foo' => 'bar'],
            recordedAtUtc: new \DateTimeImmutable('2026-08-20 11:00:00'),
            recordedByUserId: self::USER_ID,
        );

        $updatedAt = new \DateTimeImmutable('2026-08-20 12:30:00');
        $updated   = $record->withStatus(ExamStatus::NORMAL, $updatedAt);

        self::assertNotSame($record, $updated);
        self::assertSame(ExamStatus::NORMAL, $updated->getStatus());
        self::assertSame($updatedAt, $updated->getRecordedAtUtc());
        self::assertSame($record->getId(), $updated->getId());
        self::assertSame(BodySystem::DENTAL, $updated->getSystem());
        self::assertSame('Non coopératif', $updated->getNotes());
        self::assertSame(['foo' => 'bar'], $updated->getStructuredData());
        self::assertSame(self::USER_ID, $updated->getRecordedByUserId());
        self::assertSame(ExamStatus::UNTESTED, $record->getStatus());
    }
}
