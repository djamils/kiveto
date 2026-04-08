<?php

declare(strict_types=1);

namespace App\Tests\Unit\ClinicalCare\Domain\ValueObject;

use App\ClinicalCare\Domain\ValueObject\PerformedActRecord;
use App\ClinicalCare\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class PerformedActRecordTest extends TestCase
{
    private const string USER_ID = '11111111-1111-4111-8111-111111111111';

    public function testCreate(): void
    {
        $performedAt = new \DateTimeImmutable('2026-04-10 09:20:00');
        $createdAt   = new \DateTimeImmutable('2026-04-10 09:21:00');

        $act = PerformedActRecord::create(
            'Vaccine injection',
            1.0,
            $performedAt,
            $createdAt,
            UserId::fromString(self::USER_ID),
        );

        self::assertNotEmpty($act->getId());
        self::assertSame('Vaccine injection', $act->getLabel());
        self::assertSame(1.0, $act->getQuantity());
        self::assertSame($performedAt, $act->getPerformedAtUtc());
        self::assertSame($createdAt, $act->getCreatedAtUtc());
        self::assertSame(self::USER_ID, $act->getCreatedByUserId());
    }

    public function testCreateRejectsEmptyLabel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Act label cannot be empty');

        PerformedActRecord::create(
            '   ',
            1.0,
            new \DateTimeImmutable('2026-04-10 09:20:00'),
            new \DateTimeImmutable('2026-04-10 09:20:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateRejectsZeroQuantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Act quantity must be positive');

        PerformedActRecord::create(
            'Vaccine',
            0.0,
            new \DateTimeImmutable('2026-04-10 09:20:00'),
            new \DateTimeImmutable('2026-04-10 09:20:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateRejectsNegativeQuantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Act quantity must be positive');

        PerformedActRecord::create(
            'Vaccine',
            -1.0,
            new \DateTimeImmutable('2026-04-10 09:20:00'),
            new \DateTimeImmutable('2026-04-10 09:20:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testReconstitute(): void
    {
        $id          = '22222222-2222-4222-8222-222222222222';
        $performedAt = new \DateTimeImmutable('2026-04-10 09:20:00');
        $createdAt   = new \DateTimeImmutable('2026-04-10 09:21:00');

        $act = PerformedActRecord::reconstitute(
            id: $id,
            label: 'Otoscopy',
            quantity: 1.0,
            performedAtUtc: $performedAt,
            createdAtUtc: $createdAt,
            createdByUserId: self::USER_ID,
        );

        self::assertSame($id, $act->getId());
        self::assertSame('Otoscopy', $act->getLabel());
    }
}
