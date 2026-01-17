<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Domain\Event;

use App\Clinic\Domain\Event\ClinicClosed;
use PHPUnit\Framework\TestCase;

final class ClinicClosedTest extends TestCase
{
    public function testPayloadAndAggregateId(): void
    {
        $event = new ClinicClosed(clinicId: 'clinic-123');

        self::assertSame('clinic-123', $event->aggregateId());
        self::assertSame(['clinicId' => 'clinic-123'], $event->payload());
        self::assertSame('clinic.clinic.closed.v1', $event->name());
    }
}
