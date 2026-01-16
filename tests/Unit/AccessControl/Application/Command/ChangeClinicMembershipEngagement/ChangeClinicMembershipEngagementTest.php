<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Application\Command\ChangeClinicMembershipEngagement;

use App\AccessControl\Application\Command\ChangeClinicMembershipEngagement\ChangeClinicMembershipEngagement;
use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use PHPUnit\Framework\TestCase;

final class ChangeClinicMembershipEngagementTest extends TestCase
{
    public function testCommandConstruction(): void
    {
        $command = new ChangeClinicMembershipEngagement(
            membershipId: '11111111-1111-1111-1111-111111111111',
            engagement: ClinicMembershipEngagement::CONTRACTOR,
        );

        self::assertSame('11111111-1111-1111-1111-111111111111', $command->membershipId);
        self::assertSame(ClinicMembershipEngagement::CONTRACTOR, $command->engagement);
    }

    public function testCommandWithEmployeeEngagement(): void
    {
        $command = new ChangeClinicMembershipEngagement(
            membershipId: '22222222-2222-2222-2222-222222222222',
            engagement: ClinicMembershipEngagement::EMPLOYEE,
        );

        self::assertSame(ClinicMembershipEngagement::EMPLOYEE, $command->engagement);
    }

    public function testCommandIsReadonly(): void
    {
        $command = new ChangeClinicMembershipEngagement(
            membershipId: '33333333-3333-3333-3333-333333333333',
            engagement: ClinicMembershipEngagement::CONTRACTOR,
        );

        self::assertSame('33333333-3333-3333-3333-333333333333', $command->membershipId);
    }
}
