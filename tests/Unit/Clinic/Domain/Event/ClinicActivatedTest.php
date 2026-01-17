<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Domain\Event;

use App\Clinic\Domain\Event\ClinicActivated;
use PHPUnit\Framework\TestCase;

final class ClinicActivatedTest extends TestCase
{
    public function testPayloadAndAggregateId(): void
    {
        $event = new ClinicActivated(clinicId: 'clinic-123');

        self::assertSame('clinic-123', $event->aggregateId());
        self::assertSame(['clinicId' => 'clinic-123'], $event->payload());
        self::assertSame('clinic.clinic.activated.v1', $event->name());
    }
}
