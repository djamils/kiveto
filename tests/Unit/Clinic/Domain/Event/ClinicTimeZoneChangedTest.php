<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Domain\Event;

use App\Clinic\Domain\Event\ClinicTimeZoneChanged;
use PHPUnit\Framework\TestCase;

final class ClinicTimeZoneChangedTest extends TestCase
{
    public function testPayloadAndAggregateId(): void
    {
        $event = new ClinicTimeZoneChanged(
            clinicId: 'clinic-123',
            newTimeZone: 'America/New_York',
        );

        self::assertSame('clinic-123', $event->aggregateId());
        self::assertSame(
            [
                'clinicId'    => 'clinic-123',
                'newTimeZone' => 'America/New_York',
            ],
            $event->payload(),
        );
        self::assertSame('clinic.clinic-time-zone.changed.v1', $event->type());
    }
}
