<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Domain\Event;

use App\Clinic\Domain\Event\ClinicSlugChanged;
use PHPUnit\Framework\TestCase;

final class ClinicSlugChangedTest extends TestCase
{
    public function testPayloadAndAggregateId(): void
    {
        $event = new ClinicSlugChanged(
            clinicId: 'clinic-123',
            newSlug: 'new-slug',
        );

        self::assertSame('clinic-123', $event->aggregateId());
        self::assertSame(
            [
                'clinicId' => 'clinic-123',
                'newSlug'  => 'new-slug',
            ],
            $event->payload(),
        );
        self::assertSame('clinic.clinic-slug.changed.v1', $event->type());
    }
}
