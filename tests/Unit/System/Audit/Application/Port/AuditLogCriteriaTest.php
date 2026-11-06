<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Audit\Application\Port;

use App\System\Audit\Application\Port\AuditLogCriteria;
use App\System\Audit\Domain\ValueObject\ActorKind;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use PHPUnit\Framework\TestCase;

final class AuditLogCriteriaTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-000000000c01';
    private const string USER   = '01960000-0000-7000-8000-000000000b01';

    public function testThePeriodStartsThatManyDaysBeforeNow(): void
    {
        $criteria = AuditLogCriteria::fromFilters(
            AuditCategory::SUPPORT,
            self::CLINIC,
            ActorKind::KIVETO,
            self::USER,
            7,
            '  prado ',
            new \DateTimeImmutable('2026-11-06T10:00:00Z'),
        );

        self::assertSame(AuditCategory::SUPPORT, $criteria->category);
        self::assertSame(self::CLINIC, $criteria->clinicId);
        self::assertSame(ActorKind::KIVETO, $criteria->actorKind);
        self::assertSame(self::USER, $criteria->userId);
        self::assertSame('2026-10-30T10:00:00+00:00', $criteria->since?->format(\DATE_ATOM));
        self::assertSame('prado', $criteria->text);
    }

    public function testNoPeriodAndABlankTextFilterNothing(): void
    {
        $now = new \DateTimeImmutable('2026-11-06T10:00:00Z');

        self::assertNull(AuditLogCriteria::fromFilters(null, null, null, null, null, '  ', $now)->text);
        self::assertNull(AuditLogCriteria::fromFilters(null, null, null, null, null, null, $now)->since);
        self::assertNull(AuditLogCriteria::fromFilters(null, null, null, null, 0, null, $now)->since);
    }
}
