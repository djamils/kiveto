<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Application\Command\ChangeClinicMembershipRole;

use App\AccessControl\Application\Command\ChangeClinicMembershipRole\ChangeClinicMembershipRole;
use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use PHPUnit\Framework\TestCase;

final class ChangeClinicMembershipRoleTest extends TestCase
{
    public function testCommandConstruction(): void
    {
        $command = new ChangeClinicMembershipRole(
            membershipId: '11111111-1111-1111-1111-111111111111',
            role: ClinicMemberRole::CLINIC_ADMIN,
        );

        self::assertSame('11111111-1111-1111-1111-111111111111', $command->membershipId);
        self::assertSame(ClinicMemberRole::CLINIC_ADMIN, $command->role);
    }

    public function testCommandWithVeterinaryRole(): void
    {
        $command = new ChangeClinicMembershipRole(
            membershipId: '22222222-2222-2222-2222-222222222222',
            role: ClinicMemberRole::VETERINARY,
        );

        self::assertSame(ClinicMemberRole::VETERINARY, $command->role);
    }

    public function testCommandWithAssistantRole(): void
    {
        $command = new ChangeClinicMembershipRole(
            membershipId: '33333333-3333-3333-3333-333333333333',
            role: ClinicMemberRole::ASSISTANT_VETERINARY,
        );

        self::assertSame(ClinicMemberRole::ASSISTANT_VETERINARY, $command->role);
    }

    public function testCommandIsReadonly(): void
    {
        $command = new ChangeClinicMembershipRole(
            membershipId: '44444444-4444-4444-4444-444444444444',
            role: ClinicMemberRole::VETERINARY,
        );

        self::assertSame('44444444-4444-4444-4444-444444444444', $command->membershipId);
    }
}
