<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Staff\CreateClinicMembership;

use App\Context\Clinic\Application\Command\Staff\CreateClinicMembership\CreateClinicMembership;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use PHPUnit\Framework\TestCase;

final class CreateClinicMembershipTest extends TestCase
{
    public function testConstructWithAllParameters(): void
    {
        $validFrom  = new \DateTimeImmutable('2026-01-01');
        $validUntil = new \DateTimeImmutable('2026-12-31');

        $command = new CreateClinicMembership(
            clinicId: 'clinic-uuid',
            userId: 'user-uuid',
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: $validFrom,
            validUntil: $validUntil,
        );

        self::assertSame('clinic-uuid', $command->clinicId);
        self::assertSame('user-uuid', $command->userId);
        self::assertSame(ClinicMemberRole::VETERINARY, $command->role);
        self::assertSame(ClinicMembershipEngagement::EMPLOYEE, $command->engagement);
        self::assertSame($validFrom, $command->validFrom);
        self::assertSame($validUntil, $command->validUntil);
    }

    public function testConstructWithDefaults(): void
    {
        $command = new CreateClinicMembership(
            clinicId: 'clinic-uuid',
            userId: 'user-uuid',
            role: ClinicMemberRole::MANAGER,
            engagement: ClinicMembershipEngagement::CONTRACTOR,
        );

        self::assertNull($command->validFrom);
        self::assertNull($command->validUntil);
    }
}
