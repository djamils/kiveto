<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Domain\Event;

use App\Clinic\Domain\Event\ClinicGroupCreated;
use PHPUnit\Framework\TestCase;

final class ClinicGroupCreatedTest extends TestCase
{
    public function testPayloadAndAggregateId(): void
    {
        $event = new ClinicGroupCreated(
            clinicGroupId: 'group-123',
            name: 'Group Name',
        );

        self::assertSame('group-123', $event->aggregateId());
        self::assertSame(
            [
                'clinicGroupId' => 'group-123',
                'name'          => 'Group Name',
            ],
            $event->payload(),
        );
        self::assertSame('clinic.clinic-group.created.v1', $event->type());
    }
}
