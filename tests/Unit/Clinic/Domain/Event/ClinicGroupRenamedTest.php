<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Domain\Event;

use App\Clinic\Domain\Event\ClinicGroupRenamed;
use PHPUnit\Framework\TestCase;

final class ClinicGroupRenamedTest extends TestCase
{
    public function testPayloadAndAggregateId(): void
    {
        $event = new ClinicGroupRenamed(
            clinicGroupId: 'group-123',
            newName: 'New Group Name',
        );

        self::assertSame('group-123', $event->aggregateId());
        self::assertSame(
            [
                'clinicGroupId' => 'group-123',
                'newName'       => 'New Group Name',
            ],
            $event->payload(),
        );
        self::assertSame('clinic.clinic-group.renamed.v1', $event->name());
    }
}
