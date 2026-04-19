<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Staff\RegisterVeterinaryCredentials;

use App\Context\Clinic\Application\Command\Staff\RegisterVeterinaryCredentials\RegisterVeterinaryCredentials;
use App\Context\Clinic\Application\Command\Staff\RegisterVeterinaryCredentials\RegisterVeterinaryCredentialsHandler;
use App\Context\Clinic\Application\Exception\CannotRegisterCredentialsForNonVeterinarianRole;
use App\Context\Clinic\Domain\Staff\ClinicMembership;
use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Clinic\Domain\Staff\Repository\StaffProfileRepositoryInterface;
use App\Context\Clinic\Domain\Staff\StaffProfile;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipStatus;
use App\Context\Clinic\Domain\Staff\ValueObject\DisplayName;
use App\Context\Clinic\Domain\Staff\ValueObject\HexColor;
use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalTitle;
use App\Context\Clinic\Domain\Staff\ValueObject\StaffProfileId;
use App\Context\Clinic\Domain\Staff\ValueObject\UserId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class RegisterVeterinaryCredentialsHandlerTest extends TestCase
{
    private const string PROFILE_ID    = '01912345-6789-7abc-8def-000000000010';
    private const string MEMBERSHIP_ID = '01912345-6789-7abc-8def-000000000020';

    public function testRegistersCredentialsForVeterinaryRole(): void
    {
        $profile    = $this->makeProfile();
        $membership = $this->makeMembership(ClinicMemberRole::VETERINARY);

        $profileRepo = $this->createMock(StaffProfileRepositoryInterface::class);
        $profileRepo->method('findById')->willReturn($profile);
        $profileRepo->expects(self::once())->method('save');

        $membershipRepo = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $membershipRepo->method('findById')->willReturn($membership);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-06-01T00:00:00Z'));

        $handler = new RegisterVeterinaryCredentialsHandler($profileRepo, $membershipRepo, $clock);

        $handler(new RegisterVeterinaryCredentials(
            profileId: self::PROFILE_ID,
            registrationNumber: 'FR-12345',
            professionalTitle: ProfessionalTitle::DR,
        ));

        self::assertTrue($profile->hasVeterinaryCredentials());
        self::assertSame('FR-12345', $profile->credentials()?->registrationNumber()->toString());
    }

    public function testThrowsForNonVeterinarianRole(): void
    {
        $profile    = $this->makeProfile();
        $membership = $this->makeMembership(ClinicMemberRole::VETERINARY_ASSISTANT);

        $profileRepo = $this->createStub(StaffProfileRepositoryInterface::class);
        $profileRepo->method('findById')->willReturn($profile);

        $membershipRepo = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $membershipRepo->method('findById')->willReturn($membership);

        $clock   = $this->createStub(ClockInterface::class);
        $handler = new RegisterVeterinaryCredentialsHandler($profileRepo, $membershipRepo, $clock);

        $this->expectException(CannotRegisterCredentialsForNonVeterinarianRole::class);

        $handler(new RegisterVeterinaryCredentials(
            profileId: self::PROFILE_ID,
            registrationNumber: 'FR-12345',
            professionalTitle: ProfessionalTitle::DR,
        ));
    }

    private function makeProfile(): StaffProfile
    {
        $now = new \DateTimeImmutable('2026-01-01T00:00:00Z');

        return StaffProfile::create(
            id: StaffProfileId::fromString(self::PROFILE_ID),
            membershipId: ClinicMembershipId::fromString(self::MEMBERSHIP_ID),
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

    private function makeMembership(ClinicMemberRole $role): ClinicMembership
    {
        return ClinicMembership::reconstitute(
            id: ClinicMembershipId::fromString(self::MEMBERSHIP_ID),
            clinicId: ClinicId::fromString('01960000-0000-7000-8000-000000000001'),
            userId: UserId::fromString('01912345-6789-7abc-8def-000000000002'),
            role: $role,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            status: ClinicMembershipStatus::ACTIVE,
            validFrom: new \DateTimeImmutable('2026-01-01'),
            validUntil: null,
            createdAt: new \DateTimeImmutable('2026-01-01'),
            isDefault: false,
        );
    }
}
