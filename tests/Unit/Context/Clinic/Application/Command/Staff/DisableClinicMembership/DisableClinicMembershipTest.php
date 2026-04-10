<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Staff\DisableClinicMembership;

use App\Context\Clinic\Application\Command\Staff\DisableClinicMembership\DisableClinicMembership;
use PHPUnit\Framework\TestCase;

final class DisableClinicMembershipTest extends TestCase
{
    public function testConstruct(): void
    {
        $command = new DisableClinicMembership('membership-uuid');

        self::assertSame('membership-uuid', $command->membershipId);
    }
}
