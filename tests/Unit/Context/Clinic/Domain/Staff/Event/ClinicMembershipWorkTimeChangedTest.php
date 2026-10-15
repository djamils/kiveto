<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\Staff\Event;

use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipWorkTimeChanged;
use PHPUnit\Framework\TestCase;

final class ClinicMembershipWorkTimeChangedTest extends TestCase
{
    public function testPayloadAndAggregateId(): void
    {
        $event = new ClinicMembershipWorkTimeChanged('mid', 'cid', 'uid', 80);

        self::assertSame('mid', $event->aggregateId());
        self::assertSame(
            ['membershipId' => 'mid', 'clinicId' => 'cid', 'userId' => 'uid', 'workTimePercent' => 80],
            $event->payload(),
        );
    }

    public function testEventName(): void
    {
        $event = new ClinicMembershipWorkTimeChanged('m', 'c', 'u', 50);

        self::assertSame('clinic-staff.clinic-membership-work-time.changed.v1', $event->name());
    }
}
