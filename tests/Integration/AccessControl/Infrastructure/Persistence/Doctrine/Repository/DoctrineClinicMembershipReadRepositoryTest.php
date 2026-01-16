<?php

declare(strict_types=1);

namespace App\Tests\Integration\AccessControl\Infrastructure\Persistence\Doctrine\Repository;

use App\AccessControl\Application\Port\ClinicMembershipReadRepositoryInterface;
use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use App\AccessControl\Domain\ValueObject\ClinicMembershipStatus;
use App\AccessControl\Domain\ValueObject\UserId;
use App\Fixtures\AccessControl\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\IdentityAccess\Factory\ClinicUserFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineClinicMembershipReadRepositoryTest extends KernelTestCase
{
    use Factories;

    private ClinicMembershipReadRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $repository = static::getContainer()->get(ClinicMembershipReadRepositoryInterface::class);
        \assert($repository instanceof ClinicMembershipReadRepositoryInterface);
        $this->repository = $repository;
    }

    public function testFindAccessibleClinicsForUserReturnsEmptyWhenNoMemberships(): void
    {
        $userId = '11111111-1111-1111-1111-111111111111';

        $result = $this->repository->findAccessibleClinicsForUser(UserId::fromString($userId));

        self::assertCount(0, $result);
    }

    public function testFindAccessibleClinicsForUserReturnsActiveMembershipsWithActiveClinic(): void
    {
        $userId   = '11111111-1111-1111-1111-111111111111';
        $clinicId = '22222222-2222-2222-2222-222222222222';

        ClinicUserFactory::createOne([
            'id'    => Uuid::fromString($userId),
            'email' => 'user@example.com',
        ]);

        ClinicEntityFactory::createOne([
            'id'     => Uuid::fromString($clinicId),
            'name'   => 'Test Clinic',
            'slug'   => 'test-clinic',
            'status' => \App\Clinic\Domain\ValueObject\ClinicStatus::ACTIVE,
        ]);

        ClinicMembershipEntityFactory::createOne([
            'userId'     => Uuid::fromString($userId),
            'clinicId'   => Uuid::fromString($clinicId),
            'role'       => ClinicMemberRole::VETERINARY,
            'engagement' => ClinicMembershipEngagement::EMPLOYEE,
            'status'     => ClinicMembershipStatus::ACTIVE,
            'validFrom'  => new \DateTimeImmutable('-1 day'),
            'validUntil' => null,
        ]);

        $result = $this->repository->findAccessibleClinicsForUser(UserId::fromString($userId));

        self::assertCount(1, $result);
        self::assertSame($clinicId, $result[0]->clinicId);
        self::assertSame('Test Clinic', $result[0]->clinicName);
        self::assertSame('test-clinic', $result[0]->clinicSlug);
        self::assertSame('active', $result[0]->clinicStatus);
        self::assertSame(ClinicMemberRole::VETERINARY, $result[0]->memberRole);
        self::assertSame(ClinicMembershipEngagement::EMPLOYEE, $result[0]->engagement);
        self::assertNull($result[0]->validUntil);
    }

    public function testFindAccessibleClinicsForUserExcludesDisabledMemberships(): void
    {
        $userId   = '11111111-1111-1111-1111-111111111111';
        $clinicId = '22222222-2222-2222-2222-222222222222';

        ClinicUserFactory::createOne([
            'id'    => Uuid::fromString($userId),
            'email' => 'user@example.com',
        ]);

        ClinicEntityFactory::createOne([
            'id'   => Uuid::fromString($clinicId),
            'name' => 'Test Clinic',
            'slug' => 'test-clinic',
        ]);

        ClinicMembershipEntityFactory::createOne([
            'userId'     => Uuid::fromString($userId),
            'clinicId'   => Uuid::fromString($clinicId),
            'status'     => ClinicMembershipStatus::DISABLED,
            'validFrom'  => new \DateTimeImmutable('-1 day'),
            'validUntil' => null,
        ]);

        $result = $this->repository->findAccessibleClinicsForUser(UserId::fromString($userId));

        self::assertCount(0, $result);
    }

    public function testFindAccessibleClinicsForUserExcludesSuspendedClinics(): void
    {
        $userId   = '11111111-1111-1111-1111-111111111111';
        $clinicId = '22222222-2222-2222-2222-222222222222';

        ClinicUserFactory::createOne([
            'id'    => Uuid::fromString($userId),
            'email' => 'user@example.com',
        ]);

        ClinicEntityFactory::createOne([
            'id'     => Uuid::fromString($clinicId),
            'name'   => 'Suspended Clinic',
            'slug'   => 'suspended-clinic',
            'status' => \App\Clinic\Domain\ValueObject\ClinicStatus::SUSPENDED,
        ]);

        ClinicMembershipEntityFactory::createOne([
            'userId'     => Uuid::fromString($userId),
            'clinicId'   => Uuid::fromString($clinicId),
            'status'     => ClinicMembershipStatus::ACTIVE,
            'validFrom'  => new \DateTimeImmutable('-1 day'),
            'validUntil' => null,
        ]);

        $result = $this->repository->findAccessibleClinicsForUser(UserId::fromString($userId));

        self::assertCount(0, $result);
    }

    public function testFindAccessibleClinicsForUserExcludesNotYetValidMemberships(): void
    {
        $userId   = '11111111-1111-1111-1111-111111111111';
        $clinicId = '22222222-2222-2222-2222-222222222222';

        ClinicUserFactory::createOne([
            'id'    => Uuid::fromString($userId),
            'email' => 'user@example.com',
        ]);

        ClinicEntityFactory::createOne([
            'id'   => Uuid::fromString($clinicId),
            'name' => 'Test Clinic',
            'slug' => 'test-clinic',
        ]);

        ClinicMembershipEntityFactory::createOne([
            'userId'     => Uuid::fromString($userId),
            'clinicId'   => Uuid::fromString($clinicId),
            'status'     => ClinicMembershipStatus::ACTIVE,
            'validFrom'  => new \DateTimeImmutable('+1 day'),
            'validUntil' => new \DateTimeImmutable('+1 month'),
        ]);

        $result = $this->repository->findAccessibleClinicsForUser(UserId::fromString($userId));

        self::assertCount(0, $result);
    }

    public function testFindAccessibleClinicsForUserExcludesExpiredMemberships(): void
    {
        $userId   = '11111111-1111-1111-1111-111111111111';
        $clinicId = '22222222-2222-2222-2222-222222222222';

        ClinicUserFactory::createOne([
            'id'    => Uuid::fromString($userId),
            'email' => 'user@example.com',
        ]);

        ClinicEntityFactory::createOne([
            'id'   => Uuid::fromString($clinicId),
            'name' => 'Test Clinic',
            'slug' => 'test-clinic',
        ]);

        ClinicMembershipEntityFactory::createOne([
            'userId'     => Uuid::fromString($userId),
            'clinicId'   => Uuid::fromString($clinicId),
            'status'     => ClinicMembershipStatus::ACTIVE,
            'validFrom'  => new \DateTimeImmutable('-2 months'),
            'validUntil' => new \DateTimeImmutable('-1 day'),
        ]);

        $result = $this->repository->findAccessibleClinicsForUser(UserId::fromString($userId));

        self::assertCount(0, $result);
    }

    public function testFindAccessibleClinicsForUserReturnsMultipleClinicsOrderedByName(): void
    {
        $userId    = '11111111-1111-1111-1111-111111111111';
        $clinicId1 = '22222222-2222-2222-2222-222222222222';
        $clinicId2 = '33333333-3333-3333-3333-333333333333';
        $clinicId3 = '44444444-4444-4444-4444-444444444444';

        ClinicUserFactory::createOne([
            'id'    => Uuid::fromString($userId),
            'email' => 'user@example.com',
        ]);

        ClinicEntityFactory::createOne([
            'id'   => Uuid::fromString($clinicId1),
            'name' => 'Zebra Clinic',
            'slug' => 'zebra-clinic',
        ]);

        ClinicEntityFactory::createOne([
            'id'   => Uuid::fromString($clinicId2),
            'name' => 'Alpha Clinic',
            'slug' => 'alpha-clinic',
        ]);

        ClinicEntityFactory::createOne([
            'id'   => Uuid::fromString($clinicId3),
            'name' => 'Bravo Clinic',
            'slug' => 'bravo-clinic',
        ]);

        ClinicMembershipEntityFactory::createOne([
            'userId'     => Uuid::fromString($userId),
            'clinicId'   => Uuid::fromString($clinicId1),
            'status'     => ClinicMembershipStatus::ACTIVE,
            'validFrom'  => new \DateTimeImmutable('-1 day'),
            'validUntil' => null,
        ]);

        ClinicMembershipEntityFactory::createOne([
            'userId'     => Uuid::fromString($userId),
            'clinicId'   => Uuid::fromString($clinicId2),
            'status'     => ClinicMembershipStatus::ACTIVE,
            'validFrom'  => new \DateTimeImmutable('-1 day'),
            'validUntil' => null,
        ]);

        ClinicMembershipEntityFactory::createOne([
            'userId'     => Uuid::fromString($userId),
            'clinicId'   => Uuid::fromString($clinicId3),
            'status'     => ClinicMembershipStatus::ACTIVE,
            'validFrom'  => new \DateTimeImmutable('-1 day'),
            'validUntil' => null,
        ]);

        $result = $this->repository->findAccessibleClinicsForUser(UserId::fromString($userId));

        self::assertCount(3, $result);
        self::assertSame('Alpha Clinic', $result[0]->clinicName);
        self::assertSame('Bravo Clinic', $result[1]->clinicName);
        self::assertSame('Zebra Clinic', $result[2]->clinicName);
    }

    public function testFindAccessibleClinicsForUserPreservesAllFieldsIncludingContractorValidUntil(): void
    {
        $userId   = '11111111-1111-1111-1111-111111111111';
        $clinicId = '22222222-2222-2222-2222-222222222222';

        $validFrom  = new \DateTimeImmutable('2024-01-01 10:00:00');
        $validUntil = new \DateTimeImmutable('2027-12-31 23:59:59');

        ClinicUserFactory::createOne([
            'id'    => Uuid::fromString($userId),
            'email' => 'user@example.com',
        ]);

        ClinicEntityFactory::createOne([
            'id'   => Uuid::fromString($clinicId),
            'name' => 'Test Clinic',
            'slug' => 'test-clinic',
        ]);

        ClinicMembershipEntityFactory::createOne([
            'userId'     => Uuid::fromString($userId),
            'clinicId'   => Uuid::fromString($clinicId),
            'role'       => ClinicMemberRole::CLINIC_ADMIN,
            'engagement' => ClinicMembershipEngagement::CONTRACTOR,
            'status'     => ClinicMembershipStatus::ACTIVE,
            'validFrom'  => $validFrom,
            'validUntil' => $validUntil,
        ]);

        $result = $this->repository->findAccessibleClinicsForUser(UserId::fromString($userId));

        self::assertCount(1, $result);
        self::assertSame(ClinicMemberRole::CLINIC_ADMIN, $result[0]->memberRole);
        self::assertSame(ClinicMembershipEngagement::CONTRACTOR, $result[0]->engagement);
        self::assertSame($validFrom->format('Y-m-d H:i:s'), $result[0]->validFrom->format('Y-m-d H:i:s'));
        self::assertNotNull($result[0]->validUntil);
        self::assertSame($validUntil->format('Y-m-d H:i:s'), $result[0]->validUntil->format('Y-m-d H:i:s'));
    }
}
