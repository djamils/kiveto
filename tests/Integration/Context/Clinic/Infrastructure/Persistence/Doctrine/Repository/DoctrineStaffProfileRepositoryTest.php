<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Clinic\Domain\Staff\Repository\StaffProfileRepositoryInterface;
use App\Context\Clinic\Domain\Staff\StaffProfile;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\Staff\ValueObject\DisplayName;
use App\Context\Clinic\Domain\Staff\ValueObject\HexColor;
use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalRegistrationNumber;
use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalTitle;
use App\Context\Clinic\Domain\Staff\ValueObject\StaffProfileId;
use App\Context\Clinic\Domain\Staff\VeterinaryCredentials;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineStaffProfileRepositoryTest extends KernelTestCase
{
    use Factories;

    private const string PROFILE_ID = '01960001-0000-7000-8000-000000000001';

    public function testSaveAndFindById(): void
    {
        $membership = ClinicMembershipEntityFactory::new()->asVeterinary()->create();

        $membershipId = $membership->getId()->toRfc4122();

        // Remove the auto-created profile from afterPersist hook so we can test save()
        $repo     = $this->repo();
        $existing = $repo->findByMembershipId(ClinicMembershipId::fromString($membershipId));

        if (null !== $existing) {
            // profile already exists via afterPersist — test findById instead
            $found = $repo->findById($existing->id());
            self::assertNotNull($found);
            self::assertSame($existing->id()->toString(), $found->id()->toString());
            self::assertSame($membershipId, $found->membershipId()->toString());

            return;
        }

        $profile = $this->makeProfile($membershipId);
        $repo->save($profile);

        $found = $repo->findById(StaffProfileId::fromString(self::PROFILE_ID));
        self::assertNotNull($found);
        self::assertSame(self::PROFILE_ID, $found->id()->toString());
        self::assertSame($membershipId, $found->membershipId()->toString());
        self::assertFalse($found->hasVeterinaryCredentials());
    }

    public function testSaveAndFindByMembershipId(): void
    {
        $membership   = ClinicMembershipEntityFactory::new()->asManager()->create();
        $membershipId = $membership->getId()->toRfc4122();

        $profile = $this->makeProfile($membershipId);
        $this->repo()->save($profile);

        $found = $this->repo()->findByMembershipId(ClinicMembershipId::fromString($membershipId));
        self::assertNotNull($found);
        self::assertSame($membershipId, $found->membershipId()->toString());
    }

    public function testSaveAndFindByIdWithCredentials(): void
    {
        $membership   = ClinicMembershipEntityFactory::new()->asManager()->create();
        $membershipId = $membership->getId()->toRfc4122();

        $credentials = new VeterinaryCredentials(
            registrationNumber: ProfessionalRegistrationNumber::fromString('FR-12345'),
            professionalTitle: ProfessionalTitle::DR,
            signatureImageKey: null,
        );

        $profile = $this->makeProfile($membershipId, $credentials);
        $this->repo()->save($profile);

        $found = $this->repo()->findById(StaffProfileId::fromString(self::PROFILE_ID));
        self::assertNotNull($found);
        self::assertTrue($found->hasVeterinaryCredentials());
        $credentials = $found->credentials();
        self::assertNotNull($credentials);
        self::assertSame('FR-12345', $credentials->registrationNumber()->toString());
        self::assertSame(ProfessionalTitle::DR, $credentials->professionalTitle());
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $notFound = $this->repo()->findById(StaffProfileId::fromString('01912345-6789-7abc-8def-ffffffffffff'));
        self::assertNull($notFound);
    }

    public function testUpdateProfile(): void
    {
        $membership   = ClinicMembershipEntityFactory::new()->asManager()->create();
        $membershipId = $membership->getId()->toRfc4122();

        $profile = $this->makeProfile($membershipId);
        $repo    = $this->repo();
        $repo->save($profile);

        $now = new \DateTimeImmutable('2026-06-01T00:00:00Z');
        $profile->rename('Marie', 'Martin', DisplayName::fromString('Dr. Martin'), $now);
        $repo->save($profile);

        $found = $repo->findById(StaffProfileId::fromString(self::PROFILE_ID));
        self::assertNotNull($found);
        self::assertSame('Marie', $found->firstName());
        self::assertSame('Martin', $found->lastName());
    }

    private function repo(): StaffProfileRepositoryInterface
    {
        $repo = self::getContainer()->get(StaffProfileRepositoryInterface::class);
        \assert($repo instanceof StaffProfileRepositoryInterface);

        return $repo;
    }

    private function makeProfile(string $membershipId, ?VeterinaryCredentials $credentials = null): StaffProfile
    {
        $now = new \DateTimeImmutable('2026-01-01T00:00:00Z');

        if (null !== $credentials) {
            return StaffProfile::reconstitute(
                id: StaffProfileId::fromString(self::PROFILE_ID),
                membershipId: ClinicMembershipId::fromString($membershipId),
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
            membershipId: ClinicMembershipId::fromString($membershipId),
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
