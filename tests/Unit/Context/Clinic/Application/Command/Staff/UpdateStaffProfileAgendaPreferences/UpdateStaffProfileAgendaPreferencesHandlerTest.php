<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Staff\UpdateStaffProfileAgendaPreferences;

use App\Context\Clinic\Application\Command\Staff\UpdateStaffProfileAgendaPreferences\UpdateStaffProfileAgendaPreferences;
use App\Context\Clinic\Application\Command\Staff\UpdateStaffProfileAgendaPreferences\UpdateStaffProfileAgendaPreferencesHandler; // phpcs:ignore Generic.Files.LineLength.TooLong
use App\Context\Clinic\Domain\Staff\Repository\StaffProfileRepositoryInterface;
use App\Context\Clinic\Domain\Staff\StaffProfile;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\Staff\ValueObject\DisplayName;
use App\Context\Clinic\Domain\Staff\ValueObject\HexColor;
use App\Context\Clinic\Domain\Staff\ValueObject\StaffProfileId;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class UpdateStaffProfileAgendaPreferencesHandlerTest extends TestCase
{
    private const string PROFILE_ID = '01912345-6789-7abc-8def-000000000010';

    public function testUpdatesColourOrderAndVisibility(): void
    {
        $profile = $this->makeProfile();

        $repo = $this->createMock(StaffProfileRepositoryInterface::class);
        $repo->method('findById')->willReturn($profile);
        $repo->expects(self::once())->method('save')->with($profile);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-06-01T00:00:00Z'));

        (new UpdateStaffProfileAgendaPreferencesHandler($repo, $clock))(new UpdateStaffProfileAgendaPreferences(
            profileId: self::PROFILE_ID,
            agendaColor: '#C67DB5',
            sortOrder: 3,
            isVisibleInAgenda: false,
        ));

        self::assertSame('#c67db5', $profile->agendaColor()->toString());
        self::assertSame(3, $profile->sortOrder());
        self::assertFalse($profile->isVisibleInAgenda());
    }

    public function testRefusesAColourThatIsNotOne(): void
    {
        $repo = $this->createStub(StaffProfileRepositoryInterface::class);
        $repo->method('findById')->willReturn($this->makeProfile());

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-06-01T00:00:00Z'));

        $this->expectException(\InvalidArgumentException::class);

        (new UpdateStaffProfileAgendaPreferencesHandler($repo, $clock))(new UpdateStaffProfileAgendaPreferences(self::PROFILE_ID, 'violet', 0, true));
    }

    public function testThrowsWhenProfileNotFound(): void
    {
        $repo = $this->createStub(StaffProfileRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);

        (new UpdateStaffProfileAgendaPreferencesHandler($repo, $this->createStub(ClockInterface::class)))(new UpdateStaffProfileAgendaPreferences(self::PROFILE_ID, '#123456', 0, true));
    }

    private function makeProfile(): StaffProfile
    {
        $now = new \DateTimeImmutable('2026-01-01T00:00:00Z');

        return StaffProfile::create(
            id: StaffProfileId::fromString(self::PROFILE_ID),
            membershipId: ClinicMembershipId::fromString('01912345-6789-7abc-8def-000000000020'),
            firstName: 'Julie',
            lastName: 'Martin',
            displayName: DisplayName::fromString('Julie Martin'),
            phoneNumber: null,
            agendaColor: HexColor::fromString('#1a2b3c'),
            sortOrder: 0,
            isVisibleInAgenda: true,
            createdAt: $now,
            updatedAt: $now,
        );
    }
}
