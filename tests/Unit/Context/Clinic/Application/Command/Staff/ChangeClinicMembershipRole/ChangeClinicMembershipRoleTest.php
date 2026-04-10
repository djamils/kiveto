<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipRole;

use App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipRole\ChangeClinicMembershipRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use PHPUnit\Framework\TestCase;

final class ChangeClinicMembershipRoleTest extends TestCase
{
    public function testConstruct(): void
    {
        $command = new ChangeClinicMembershipRole('membership-uuid', ClinicMemberRole::MANAGER);

        self::assertSame('membership-uuid', $command->membershipId);
        self::assertSame(ClinicMemberRole::MANAGER, $command->role);
    }
}
