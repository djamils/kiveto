<?php

declare(strict_types=1);

namespace App\Tests\Unit\Clinic\Domain\Event;

use App\Clinic\Domain\Event\ClinicRenamed;
use PHPUnit\Framework\TestCase;

final class ClinicRenamedTest extends TestCase
{
    public function testPayloadAndAggregateId(): void
    {
        $event = new ClinicRenamed(
            clinicId: 'clinic-123',
            newName: 'New Clinic Name',
        );

        self::assertSame('clinic-123', $event->aggregateId());
        self::assertSame(
            [
                'clinicId' => 'clinic-123',
                'newName'  => 'New Clinic Name',
            ],
            $event->payload(),
        );
        self::assertSame('clinic.clinic.renamed.v1', $event->name());
    }
}
