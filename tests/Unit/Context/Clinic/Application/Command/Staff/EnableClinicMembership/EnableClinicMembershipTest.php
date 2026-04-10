<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Staff\EnableClinicMembership;

use App\Context\Clinic\Application\Command\Staff\EnableClinicMembership\EnableClinicMembership;
use PHPUnit\Framework\TestCase;

final class EnableClinicMembershipTest extends TestCase
{
    public function testConstruct(): void
    {
        $command = new EnableClinicMembership('membership-uuid');

        self::assertSame('membership-uuid', $command->membershipId);
    }
}
