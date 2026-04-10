<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Clinic\Domain\Staff\ClinicMembership;
use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipStatus;
use App\Context\Clinic\Domain\Staff\ValueObject\UserId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Fixtures\Context\Clinic\Factory\ClinicMembershipEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineClinicMembershipRepositoryTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC_ID = '01912345-6789-7abc-8def-000000000001';
    private const string USER_ID   = '01912345-6789-7abc-8def-000000000002';

    public function testSaveAndFindById(): void
    {
        $membership = ClinicMembership::create(
            id: ClinicMembershipId::fromString('01912345-6789-7abc-8def-000000000099'),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            userId: UserId::fromString(self::USER_ID),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: new \DateTimeImmutable('2026-01-01 00:00:00'),
            validUntil: null,
            createdAt: new \DateTimeImmutable('2026-01-01 00:00:00'),
        );

        $this->repo()->save($membership);

        $found = $this->repo()->findById(ClinicMembershipId::fromString('01912345-6789-7abc-8def-000000000099'));

        self::assertNotNull($found);
        self::assertSame('01912345-6789-7abc-8def-000000000099', $found->id()->toString());
        self::assertSame(ClinicMemberRole::VETERINARY, $found->role());
        self::assertSame(ClinicMembershipEngagement::EMPLOYEE, $found->engagement());
        self::assertSame(ClinicMembershipStatus::ACTIVE, $found->status());
        self::assertFalse($found->isDefault());
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $id = ClinicMembershipId::fromString('01912345-6789-7abc-8def-000000000099');

        self::assertNull($this->repo()->findById($id));
    }

    public function testFindByClinicAndUser(): void
    {
        ClinicMembershipEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withUserId(self::USER_ID)
            ->asVeterinary()
            ->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        $found = $this->repo()->findByClinicAndUser(
            ClinicId::fromString(self::CLINIC_ID),
            UserId::fromString(self::USER_ID),
        );

        self::assertNotNull($found);
        self::assertSame(self::CLINIC_ID, $found->clinicId()->toString());
        self::assertSame(self::USER_ID, $found->userId()->toString());
    }

    public function testFindByClinicAndUserReturnsNull(): void
    {
        self::assertNull($this->repo()->findByClinicAndUser(
            ClinicId::fromString(self::CLINIC_ID),
            UserId::fromString(self::USER_ID),
        ));
    }

    public function testExistsByClinicAndUser(): void
    {
        ClinicMembershipEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withUserId(self::USER_ID)
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        self::assertTrue($this->repo()->existsByClinicAndUser(
            ClinicId::fromString(self::CLINIC_ID),
            UserId::fromString(self::USER_ID),
        ));
    }

    public function testExistsByClinicAndUserReturnsFalse(): void
    {
        self::assertFalse($this->repo()->existsByClinicAndUser(
            ClinicId::fromString(self::CLINIC_ID),
            UserId::fromString(self::USER_ID),
        ));
    }

    public function testSaveUpdatesExistingEntity(): void
    {
        $proxy = ClinicMembershipEntityFactory::new()
            ->withClinicId(self::CLINIC_ID)
            ->withUserId(self::USER_ID)
            ->asVeterinary()
            ->asEmployee()
            ->create(['validFrom' => new \DateTimeImmutable('2026-01-01')])
        ;

        $id = $proxy->getId()->toRfc4122();

        $membership = $this->repo()->findById(ClinicMembershipId::fromString($id));
        self::assertNotNull($membership);

        $membership->changeRole(ClinicMemberRole::MANAGER);
        $this->repo()->save($membership);

        $updated = $this->repo()->findById(ClinicMembershipId::fromString($id));
        self::assertNotNull($updated);
        self::assertSame(ClinicMemberRole::MANAGER, $updated->role());
    }

    private function repo(): ClinicMembershipRepositoryInterface
    {
        $repo = self::getContainer()->get(ClinicMembershipRepositoryInterface::class);
        \assert($repo instanceof ClinicMembershipRepositoryInterface);

        return $repo;
    }
}
