<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\PurgePractitionerDayLocks;

use App\Context\Scheduling\Application\Command\PurgePractitionerDayLocks\PurgePractitionerDayLocks;
use App\Context\Scheduling\Application\Command\PurgePractitionerDayLocks\PurgePractitionerDayLocksHandler;
use App\Tests\Unit\Context\Scheduling\Support\MovableClock;
use App\Tests\Unit\Context\Scheduling\Support\RecordingDayLock;
use PHPUnit\Framework\TestCase;

final class PurgePractitionerDayLocksHandlerTest extends TestCase
{
    public function testRowsOlderThanThirtyDaysArePurged(): void
    {
        $locks = new RecordingDayLock();

        $purged = new PurgePractitionerDayLocksHandler($locks, new MovableClock(new \DateTimeImmutable('2026-11-01 04:17', new \DateTimeZone('UTC'))))(new PurgePractitionerDayLocks());

        self::assertSame(3, $purged);
        self::assertSame(['2026-10-02'], $locks->purgedBefore);
    }
}
