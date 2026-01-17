<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Domain\Event;

use App\Clinic\Domain\Event\ClinicGroupActivated;
use PHPUnit\Framework\TestCase;

final class ClinicGroupActivatedTest extends TestCase
{
    public function testPayloadAndAggregateId(): void
    {
        $event = new ClinicGroupActivated(clinicGroupId: 'group-123');

        self::assertSame('group-123', $event->aggregateId());
        self::assertSame(['clinicGroupId' => 'group-123'], $event->payload());
        self::assertSame('clinic.clinic-group.activated.v1', $event->name());
    }
}
