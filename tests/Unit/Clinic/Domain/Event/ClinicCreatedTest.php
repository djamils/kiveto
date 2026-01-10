<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Domain\Event;

use App\Clinic\Domain\Event\ClinicCreated;
use PHPUnit\Framework\TestCase;

final class ClinicCreatedTest extends TestCase
{
    public function testPayloadAndAggregateId(): void
    {
        $event = new ClinicCreated(
            clinicId: 'clinic-123',
            name: 'Clinic Name',
            slug: 'clinic-slug',
            timeZone: 'Europe/Paris',
            locale: 'fr-FR',
            clinicGroupId: 'group-456',
        );

        self::assertSame('clinic-123', $event->aggregateId());
        self::assertSame(
            [
                'clinicId'      => 'clinic-123',
                'name'          => 'Clinic Name',
                'slug'          => 'clinic-slug',
                'timeZone'      => 'Europe/Paris',
                'locale'        => 'fr-FR',
                'clinicGroupId' => 'group-456',
            ],
            $event->payload(),
        );
        self::assertSame('clinic.clinic.created.v1', $event->type());
    }

    public function testPayloadWithoutClinicGroup(): void
    {
        $event = new ClinicCreated(
            clinicId: 'clinic-123',
            name: 'Clinic Name',
            slug: 'clinic-slug',
            timeZone: 'Europe/Paris',
            locale: 'fr-FR',
            clinicGroupId: null,
        );

        $payload = $event->payload();
        self::assertNull($payload['clinicGroupId']);
    }
}
