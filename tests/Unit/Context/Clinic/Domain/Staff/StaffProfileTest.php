<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\Staff;

use App\Context\Clinic\Domain\Staff\StaffProfile;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\Staff\ValueObject\DisplayName;
use App\Context\Clinic\Domain\Staff\ValueObject\HexColor;
use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalRegistrationNumber;
use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalTitle;
use App\Context\Clinic\Domain\Staff\ValueObject\SignatureImageKey;
use App\Context\Clinic\Domain\Staff\ValueObject\StaffProfileId;
use App\Context\Clinic\Domain\Staff\VeterinaryCredentials;
use PHPUnit\Framework\TestCase;

final class StaffProfileTest extends TestCase
{
    private const string PROFILE_ID    = '01912345-6789-7abc-8def-000000000010';
    private const string MEMBERSHIP_ID = '01912345-6789-7abc-8def-000000000020';

    public function testCreateSetsStateCorrectly(): void
    {
        $profile = $this->makeProfile();

        self::assertSame(self::PROFILE_ID, $profile->id()->toString());
        self::assertSame(self::MEMBERSHIP_ID, $profile->membershipId()->toString());
        self::assertSame('Sophie', $profile->firstName());
        self::assertSame('Rousseau', $profile->lastName());
        self::assertSame('Dr. Rousseau', $profile->displayName()->toString());
        self::assertNull($profile->phoneNumber());
        self::assertSame('#1a2b3c', $profile->agendaColor()->toString());
        self::assertSame(0, $profile->sortOrder());
        self::assertTrue($profile->isVisibleInAgenda());
        self::assertFalse($profile->hasVeterinaryCredentials());
        self::assertNull($profile->credentials());
        self::assertEmpty($profile->recordedDomainEvents());
    }

    public function testCreateDoesNotRecordDomainEvents(): void
    {
        $profile = $this->makeProfile();

        self::assertEmpty($profile->recordedDomainEvents());
    }

    public function testRegisterVeterinaryCredentialsIsReflectedInState(): void
    {
        $profile = $this->makeProfile();
        $now     = new \DateTimeImmutable('2026-01-02T00:00:00Z');

        $credentials = new VeterinaryCredentials(
            registrationNumber: ProfessionalRegistrationNumber::fromString('FR-12345'),
            professionalTitle: ProfessionalTitle::DR,
            signatureImageKey: null,
        );

        $profile->registerVeterinaryCredentials($credentials, $now);

        self::assertTrue($profile->hasVeterinaryCredentials());
        self::assertSame($credentials, $profile->credentials());
        self::assertSame($now, $profile->updatedAt());
    }

    public function testClearVeterinaryCredentialsRemovesCredentials(): void
    {
        $credentials = new VeterinaryCredentials(
            registrationNumber: ProfessionalRegistrationNumber::fromString('FR-12345'),
            professionalTitle: ProfessionalTitle::DR,
            signatureImageKey: null,
        );

        $profile = $this->makeProfile($credentials);
        $now     = new \DateTimeImmutable('2026-01-02T00:00:00Z');

        self::assertTrue($profile->hasVeterinaryCredentials());

        $profile->clearVeterinaryCredentials($now);

        self::assertFalse($profile->hasVeterinaryCredentials());
        self::assertNull($profile->credentials());
    }

    public function testClearVeterinaryCredentialsIsIdempotent(): void
    {
        $profile = $this->makeProfile();
        $now     = new \DateTimeImmutable('2026-01-02T00:00:00Z');

        $profile->clearVeterinaryCredentials($now);

        self::assertFalse($profile->hasVeterinaryCredentials());
        self::assertNull($profile->credentials());
    }

    public function testClearVeterinaryCredentialsTwiceIsNoOp(): void
    {
        $credentials = new VeterinaryCredentials(
            registrationNumber: ProfessionalRegistrationNumber::fromString('FR-12345'),
            professionalTitle: ProfessionalTitle::DR,
            signatureImageKey: null,
        );

        $profile = $this->makeProfile($credentials);
        $now     = new \DateTimeImmutable('2026-01-02T00:00:00Z');

        $profile->clearVeterinaryCredentials($now);
        $profile->clearVeterinaryCredentials($now);

        self::assertFalse($profile->hasVeterinaryCredentials());
    }

    public function testRenameUpdatesState(): void
    {
        $profile = $this->makeProfile();
        $now     = new \DateTimeImmutable('2026-01-02T00:00:00Z');

        $profile->rename('Marie', 'Martin', DisplayName::fromString('Dr. Martin'), $now);

        self::assertSame('Marie', $profile->firstName());
        self::assertSame('Martin', $profile->lastName());
        self::assertSame('Dr. Martin', $profile->displayName()->toString());
        self::assertSame($now, $profile->updatedAt());
    }

    public function testUpdateAgendaPreferencesUpdatesState(): void
    {
        $profile = $this->makeProfile();
        $now     = new \DateTimeImmutable('2026-01-02T00:00:00Z');

        $profile->updateAgendaPreferences(
            agendaColor: HexColor::fromString('#ff0000'),
            sortOrder: 5,
            isVisibleInAgenda: false,
            now: $now,
        );

        self::assertSame('#ff0000', $profile->agendaColor()->toString());
        self::assertSame(5, $profile->sortOrder());
        self::assertFalse($profile->isVisibleInAgenda());
        self::assertSame($now, $profile->updatedAt());
    }

    public function testReconstituteRestoresCredentials(): void
    {
        $now = new \DateTimeImmutable('2026-01-01T00:00:00Z');

        $credentials = new VeterinaryCredentials(
            registrationNumber: ProfessionalRegistrationNumber::fromString('FR-12345'),
            professionalTitle: ProfessionalTitle::DR,
            signatureImageKey: SignatureImageKey::fromString('signatures/vet.png'),
        );

        $profile = StaffProfile::reconstitute(
            id: StaffProfileId::fromString(self::PROFILE_ID),
            membershipId: ClinicMembershipId::fromString(self::MEMBERSHIP_ID),
            firstName: 'Sophie',
            lastName: 'Rousseau',
            displayName: DisplayName::fromString('Dr. Rousseau'),
            phoneNumber: null,
            agendaColor: HexColor::fromString('#1a2b3c'),
            sortOrder: 0,
            isVisibleInAgenda: true,
            credentials: $credentials,
            createdAt: $now,
            updatedAt: $now,
        );

        self::assertTrue($profile->hasVeterinaryCredentials());
        self::assertSame('FR-12345', $profile->credentials()?->registrationNumber()->toString());
        self::assertEmpty($profile->recordedDomainEvents());
    }

    private function makeProfile(?VeterinaryCredentials $credentials = null): StaffProfile
    {
        $now = new \DateTimeImmutable('2026-01-01T00:00:00Z');

        if (null !== $credentials) {
            return StaffProfile::reconstitute(
                id: StaffProfileId::fromString(self::PROFILE_ID),
                membershipId: ClinicMembershipId::fromString(self::MEMBERSHIP_ID),
                firstName: 'Sophie',
                lastName: 'Rousseau',
                displayName: DisplayName::fromString('Dr. Rousseau'),
                phoneNumber: null,
                agendaColor: HexColor::fromString('#1a2b3c'),
                sortOrder: 0,
                isVisibleInAgenda: true,
                credentials: $credentials,
                createdAt: $now,
                updatedAt: $now,
            );
        }

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
}
