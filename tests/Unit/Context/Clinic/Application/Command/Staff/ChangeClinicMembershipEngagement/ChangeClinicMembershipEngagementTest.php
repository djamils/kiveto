<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipEngagement;

use App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipEngagement\ChangeClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use PHPUnit\Framework\TestCase;

final class ChangeClinicMembershipEngagementTest extends TestCase
{
    public function testConstruct(): void
    {
        $command = new ChangeClinicMembershipEngagement('membership-uuid', ClinicMembershipEngagement::CONTRACTOR);

        self::assertSame('membership-uuid', $command->membershipId);
        self::assertSame(ClinicMembershipEngagement::CONTRACTOR, $command->engagement);
    }
}
