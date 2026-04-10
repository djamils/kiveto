<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\Staff\Event;

use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipDisabled;
use PHPUnit\Framework\TestCase;

final class ClinicMembershipDisabledTest extends TestCase
{
    public function testPayloadAndAggregateId(): void
    {
        $event = new ClinicMembershipDisabled('mid', 'cid', 'uid');

        self::assertSame('mid', $event->aggregateId());

        $payload = $event->payload();
        self::assertSame('mid', $payload['membershipId']);
        self::assertSame('cid', $payload['clinicId']);
        self::assertSame('uid', $payload['userId']);
    }
}
