<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\Event;

use App\Context\Clinic\Domain\Event\ClinicGroupSuspended;
use PHPUnit\Framework\TestCase;

final class ClinicGroupSuspendedTest extends TestCase
{
    public function testPayloadAndAggregateId(): void
    {
        $event = new ClinicGroupSuspended(clinicGroupId: 'group-123');

        self::assertSame('group-123', $event->aggregateId());
        self::assertSame(['clinicGroupId' => 'group-123'], $event->payload());
        self::assertSame('clinic.clinic-group.suspended.v1', $event->name());
    }
}
