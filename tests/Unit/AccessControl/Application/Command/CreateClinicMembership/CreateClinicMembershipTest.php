<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Application\Command\CreateClinicMembership;

use App\AccessControl\Application\Command\CreateClinicMembership\CreateClinicMembership;
use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use PHPUnit\Framework\TestCase;

final class CreateClinicMembershipTest extends TestCase
{
    public function testCommandConstructionWithAllParameters(): void
    {
        $validFrom  = new \DateTimeImmutable('2024-01-01');
        $validUntil = new \DateTimeImmutable('2025-01-01');

        $command = new CreateClinicMembership(
            clinicId: '11111111-1111-1111-1111-111111111111',
            userId: '22222222-2222-2222-2222-222222222222',
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: $validFrom,
            validUntil: $validUntil,
        );

        self::assertSame('11111111-1111-1111-1111-111111111111', $command->clinicId);
        self::assertSame('22222222-2222-2222-2222-222222222222', $command->userId);
        self::assertSame(ClinicMemberRole::VETERINARY, $command->role);
        self::assertSame(ClinicMembershipEngagement::EMPLOYEE, $command->engagement);
        self::assertSame($validFrom, $command->validFrom);
        self::assertSame($validUntil, $command->validUntil);
    }

    public function testCommandConstructionWithDefaultDates(): void
    {
        $command = new CreateClinicMembership(
            clinicId: '11111111-1111-1111-1111-111111111111',
            userId: '22222222-2222-2222-2222-222222222222',
            role: ClinicMemberRole::CLINIC_ADMIN,
            engagement: ClinicMembershipEngagement::CONTRACTOR,
        );

        self::assertSame('11111111-1111-1111-1111-111111111111', $command->clinicId);
        self::assertSame('22222222-2222-2222-2222-222222222222', $command->userId);
        self::assertSame(ClinicMemberRole::CLINIC_ADMIN, $command->role);
        self::assertSame(ClinicMembershipEngagement::CONTRACTOR, $command->engagement);
        self::assertNull($command->validFrom);
        self::assertNull($command->validUntil);
    }

    public function testCommandWithNullValidUntil(): void
    {
        $validFrom = new \DateTimeImmutable('2024-01-01');

        $command = new CreateClinicMembership(
            clinicId: '33333333-3333-3333-3333-333333333333',
            userId: '44444444-4444-4444-4444-444444444444',
            role: ClinicMemberRole::ASSISTANT_VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: $validFrom,
            validUntil: null,
        );

        self::assertSame($validFrom, $command->validFrom);
        self::assertNull($command->validUntil);
    }

    public function testCommandIsReadonly(): void
    {
        $command = new CreateClinicMembership(
            clinicId: '55555555-5555-5555-5555-555555555555',
            userId: '66666666-6666-6666-6666-666666666666',
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
        );

        self::assertSame('55555555-5555-5555-5555-555555555555', $command->clinicId);
        self::assertSame('66666666-6666-6666-6666-666666666666', $command->userId);
    }
}
