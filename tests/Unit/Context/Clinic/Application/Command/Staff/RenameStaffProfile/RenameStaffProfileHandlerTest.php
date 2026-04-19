<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Staff\RenameStaffProfile;

use App\Context\Clinic\Application\Command\Staff\RenameStaffProfile\RenameStaffProfile;
use App\Context\Clinic\Application\Command\Staff\RenameStaffProfile\RenameStaffProfileHandler;
use App\Context\Clinic\Domain\Staff\Repository\StaffProfileRepositoryInterface;
use App\Context\Clinic\Domain\Staff\StaffProfile;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\Staff\ValueObject\DisplayName;
use App\Context\Clinic\Domain\Staff\ValueObject\HexColor;
use App\Context\Clinic\Domain\Staff\ValueObject\StaffProfileId;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class RenameStaffProfileHandlerTest extends TestCase
{
    private const string PROFILE_ID = '01912345-6789-7abc-8def-000000000010';

    public function testRenamesProfileSuccessfully(): void
    {
        $profile = $this->makeProfile();

        $profileRepo = $this->createMock(StaffProfileRepositoryInterface::class);
        $profileRepo->method('findById')->willReturn($profile);
        $profileRepo->expects(self::once())->method('save');

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-06-01T00:00:00Z'));

        $handler = new RenameStaffProfileHandler($profileRepo, $clock);

        $handler(new RenameStaffProfile(
            profileId: self::PROFILE_ID,
            firstName: 'Marie',
            lastName: 'Martin',
            displayName: 'Dr. Martin',
        ));

        self::assertSame('Marie', $profile->firstName());
        self::assertSame('Martin', $profile->lastName());
        self::assertSame('Dr. Martin', $profile->displayName()->toString());
    }

    public function testThrowsWhenProfileNotFound(): void
    {
        $profileRepo = $this->createStub(StaffProfileRepositoryInterface::class);
        $profileRepo->method('findById')->willReturn(null);

        $clock   = $this->createStub(ClockInterface::class);
        $handler = new RenameStaffProfileHandler($profileRepo, $clock);

        $this->expectException(\InvalidArgumentException::class);

        $handler(new RenameStaffProfile(
            profileId: self::PROFILE_ID,
            firstName: 'Marie',
            lastName: 'Martin',
            displayName: 'Dr. Martin',
        ));
    }

    private function makeProfile(): StaffProfile
    {
        $now = new \DateTimeImmutable('2026-01-01T00:00:00Z');

        return StaffProfile::create(
            id: StaffProfileId::fromString(self::PROFILE_ID),
            membershipId: ClinicMembershipId::fromString('01912345-6789-7abc-8def-000000000020'),
            firstName: 'Sophie',
            lastName: 'Rousseau',
            displayName: DisplayName::fromString('Dr. Rousseau'),
            phoneNumber: null,
            agendaColor: HexColor::fromString('#1a2b3c'),
            sortOrder: 0,
            isVisibleInAgenda: true,
            createdAt: $now,
            updatedAt: $now,
        );
    }
}
