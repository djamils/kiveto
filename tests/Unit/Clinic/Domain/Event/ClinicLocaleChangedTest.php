<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Domain\Event;

use App\Clinic\Domain\Event\ClinicLocaleChanged;
use PHPUnit\Framework\TestCase;

final class ClinicLocaleChangedTest extends TestCase
{
    public function testPayloadAndAggregateId(): void
    {
        $event = new ClinicLocaleChanged(
            clinicId: 'clinic-123',
            newLocale: 'en-US',
        );

        self::assertSame('clinic-123', $event->aggregateId());
        self::assertSame(
            [
                'clinicId'  => 'clinic-123',
                'newLocale' => 'en-US',
            ],
            $event->payload(),
        );
        self::assertSame('clinic.clinic-locale.changed.v1', $event->name());
    }
}
