<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClinicalCare\Domain\ValueObject;

use App\Context\ClinicalCare\Domain\ValueObject\ClinicalNoteRecord;
use App\Context\ClinicalCare\Domain\ValueObject\NoteType;
use App\Context\ClinicalCare\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class ClinicalNoteRecordTest extends TestCase
{
    private const string USER_ID = '11111111-1111-4111-8111-111111111111';

    public function testCreate(): void
    {
        $createdAt = new \DateTimeImmutable('2026-04-10 09:15:00');

        $note = ClinicalNoteRecord::create(
            NoteType::DIAGNOSIS,
            'Otitis externa',
            $createdAt,
            UserId::fromString(self::USER_ID),
        );

        self::assertNotEmpty($note->getId());
        self::assertSame(NoteType::DIAGNOSIS, $note->getNoteType());
        self::assertSame('Otitis externa', $note->getContent());
        self::assertSame($createdAt, $note->getCreatedAtUtc());
        self::assertSame(self::USER_ID, $note->getCreatedByUserId());
    }

    public function testCreateRejectsEmptyContent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Clinical note content cannot be empty');

        ClinicalNoteRecord::create(
            NoteType::GENERAL,
            '   ',
            new \DateTimeImmutable('2026-04-10 09:15:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testReconstitute(): void
    {
        $id        = '22222222-2222-4222-8222-222222222222';
        $createdAt = new \DateTimeImmutable('2026-04-10 09:15:00');

        $note = ClinicalNoteRecord::reconstitute(
            id: $id,
            noteType: NoteType::TREATMENT_PLAN,
            content: 'Daily ear cleaning',
            createdAtUtc: $createdAt,
            createdByUserId: self::USER_ID,
        );

        self::assertSame($id, $note->getId());
        self::assertSame(NoteType::TREATMENT_PLAN, $note->getNoteType());
        self::assertSame('Daily ear cleaning', $note->getContent());
    }
}
