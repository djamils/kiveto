<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\Staff\Event;

use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipAdministrationChanged;
use PHPUnit\Framework\TestCase;

final class ClinicMembershipAdministrationChangedTest extends TestCase
{
    public function testPayloadAndAggregateId(): void
    {
        $event = new ClinicMembershipAdministrationChanged(
            membershipId: 'mid',
            clinicId: 'cid',
            userId: 'uid',
            isClinicAdministrator: true,
        );

        self::assertSame('mid', $event->aggregateId());

        $payload = $event->payload();
        self::assertSame('mid', $payload['membershipId']);
        self::assertSame('cid', $payload['clinicId']);
        self::assertSame('uid', $payload['userId']);
        self::assertTrue($payload['isClinicAdministrator']);
    }

    public function testPayloadCarriesRevocation(): void
    {
        $event = new ClinicMembershipAdministrationChanged('m', 'c', 'u', false);

        self::assertFalse($event->payload()['isClinicAdministrator']);
    }

    public function testEventName(): void
    {
        $event = new ClinicMembershipAdministrationChanged('m', 'c', 'u', true);

        self::assertSame('clinic-staff.clinic-membership-administration.changed.v1', $event->name());
    }
}
