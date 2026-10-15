<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Staff\UpdateStaffProfilePhone;

use App\Context\Clinic\Application\Command\Staff\UpdateStaffProfilePhone\UpdateStaffProfilePhone;
use App\Context\Clinic\Application\Command\Staff\UpdateStaffProfilePhone\UpdateStaffProfilePhoneHandler;
use App\Context\Clinic\Domain\Staff\Repository\StaffProfileRepositoryInterface;
use App\Context\Clinic\Domain\Staff\StaffProfile;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\Staff\ValueObject\DisplayName;
use App\Context\Clinic\Domain\Staff\ValueObject\HexColor;
use App\Context\Clinic\Domain\Staff\ValueObject\StaffProfileId;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\PhoneNumber;
use PHPUnit\Framework\TestCase;

final class UpdateStaffProfilePhoneHandlerTest extends TestCase
{
    private const string PROFILE_ID = '01912345-6789-7abc-8def-000000000010';

    public function testSetsThePhoneNumber(): void
    {
        $profile = $this->makeProfile(null);

        $repo = $this->createMock(StaffProfileRepositoryInterface::class);
        $repo->method('findById')->willReturn($profile);
        $repo->expects(self::once())->method('save')->with($profile);

        (new UpdateStaffProfilePhoneHandler($repo, $this->clock()))(new UpdateStaffProfilePhone(self::PROFILE_ID, '+33612345678'));

        self::assertSame('+33612345678', $profile->phoneNumber()?->toString());
    }

    public function testClearsThePhoneNumber(): void
    {
        $profile = $this->makeProfile(PhoneNumber::fromString('+33612345678'));

        $repo = $this->createStub(StaffProfileRepositoryInterface::class);
        $repo->method('findById')->willReturn($profile);

        (new UpdateStaffProfilePhoneHandler($repo, $this->clock()))(new UpdateStaffProfilePhone(self::PROFILE_ID, null));

        self::assertNull($profile->phoneNumber());
    }

    public function testThrowsWhenProfileNotFound(): void
    {
        $repo = $this->createStub(StaffProfileRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);

        (new UpdateStaffProfilePhoneHandler($repo, $this->clock()))(new UpdateStaffProfilePhone(self::PROFILE_ID, null));
    }

    private function clock(): ClockInterface
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-06-01T00:00:00Z'));

        return $clock;
    }

    private function makeProfile(?PhoneNumber $phone): StaffProfile
    {
        $now = new \DateTimeImmutable('2026-01-01T00:00:00Z');

        return StaffProfile::create(
            id: StaffProfileId::fromString(self::PROFILE_ID),
            membershipId: ClinicMembershipId::fromString('01912345-6789-7abc-8def-000000000020'),
            firstName: 'Julie',
            lastName: 'Martin',
            displayName: DisplayName::fromString('Julie Martin'),
            phoneNumber: $phone,
            agendaColor: HexColor::fromString('#1a2b3c'),
            sortOrder: 0,
            isVisibleInAgenda: true,
            createdAt: $now,
            updatedAt: $now,
        );
    }
}
