<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Staff\ClearVeterinaryCredentials;

use App\Context\Clinic\Application\Command\Staff\ClearVeterinaryCredentials\ClearVeterinaryCredentials;
use App\Context\Clinic\Application\Command\Staff\ClearVeterinaryCredentials\ClearVeterinaryCredentialsHandler;
use App\Context\Clinic\Domain\Staff\Repository\StaffProfileRepositoryInterface;
use App\Context\Clinic\Domain\Staff\StaffProfile;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\Staff\ValueObject\DisplayName;
use App\Context\Clinic\Domain\Staff\ValueObject\HexColor;
use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalRegistrationNumber;
use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalTitle;
use App\Context\Clinic\Domain\Staff\ValueObject\StaffProfileId;
use App\Context\Clinic\Domain\Staff\VeterinaryCredentials;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class ClearVeterinaryCredentialsHandlerTest extends TestCase
{
    private const string PROFILE_ID = '01912345-6789-7abc-8def-000000000010';

    public function testClearsTheCredentials(): void
    {
        $profile = $this->makeProfile();

        $repo = $this->createMock(StaffProfileRepositoryInterface::class);
        $repo->method('findById')->willReturn($profile);
        $repo->expects(self::once())->method('save')->with($profile);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-06-01T00:00:00Z'));

        (new ClearVeterinaryCredentialsHandler($repo, $clock))(new ClearVeterinaryCredentials(self::PROFILE_ID));

        self::assertFalse($profile->hasVeterinaryCredentials());
        self::assertSame('2026-06-01T00:00:00+00:00', $profile->updatedAt()->format(\DateTimeInterface::ATOM));
    }

    public function testThrowsWhenProfileNotFound(): void
    {
        $repo = $this->createStub(StaffProfileRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);

        (new ClearVeterinaryCredentialsHandler($repo, $this->createStub(ClockInterface::class)))(new ClearVeterinaryCredentials(self::PROFILE_ID));
    }

    private function makeProfile(): StaffProfile
    {
        $now     = new \DateTimeImmutable('2026-01-01T00:00:00Z');
        $profile = StaffProfile::create(
            id: StaffProfileId::fromString(self::PROFILE_ID),
            membershipId: ClinicMembershipId::fromString('01912345-6789-7abc-8def-000000000020'),
            firstName: 'Sophie',
            lastName: 'Rousseau',
            displayName: DisplayName::fromString('Rousseau'),
            phoneNumber: null,
            agendaColor: HexColor::fromString('#1a2b3c'),
            sortOrder: 0,
            isVisibleInAgenda: true,
            createdAt: $now,
            updatedAt: $now,
        );
        $profile->registerVeterinaryCredentials(new VeterinaryCredentials(
            ProfessionalRegistrationNumber::fromString('FR-12345'),
            ProfessionalTitle::DR,
            null,
        ), $now);

        return $profile;
    }
}
