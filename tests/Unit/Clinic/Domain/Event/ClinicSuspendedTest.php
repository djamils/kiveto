<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Domain\Event;

use App\Clinic\Domain\Event\ClinicSuspended;
use PHPUnit\Framework\TestCase;

final class ClinicSuspendedTest extends TestCase
{
    public function testPayloadAndAggregateId(): void
    {
        $event = new ClinicSuspended(clinicId: 'clinic-123');

        self::assertSame('clinic-123', $event->aggregateId());
        self::assertSame(['clinicId' => 'clinic-123'], $event->payload());
        self::assertSame('clinic.clinic.suspended.v1', $event->type());
    }
}
