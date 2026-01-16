<?php

declare(strict_types=1);

namespace App\Tests\Integration\AccessControl\Infrastructure\Persistence\Doctrine\Repository;

use App\AccessControl\Application\Port\MembershipAdminRepositoryInterface;
use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use App\AccessControl\Domain\ValueObject\ClinicMembershipStatus;
use App\Fixtures\AccessControl\Factory\ClinicMembershipEntityFactory;
use App\Fixtures\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\IdentityAccess\Factory\ClinicUserFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineMembershipAdminRepositoryTest extends KernelTestCase
{
    use Factories;

    private MembershipAdminRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $repository = static::getContainer()->get(MembershipAdminRepositoryInterface::class);
        \assert($repository instanceof MembershipAdminRepositoryInterface);
        $this->repository = $repository;
    }

    public function testListAllReturnsEmptyCollectionWhenNoMemberships(): void
    {
        $result = $this->repository->listAll();

        self::assertCount(0, $result->memberships);
        self::assertSame(0, $result->total);
    }

    public function testListAllReturnsAllMembershipsOrderedByCreatedAtDesc(): void
    {
        $userId1  = '11111111-1111-1111-1111-111111111111';
        $userId2  = '22222222-2222-2222-2222-222222222222';
        $clinicId = '33333333-3333-3333-3333-333333333333';

        ClinicUserFactory::createOne([
            'id'    => Uuid::fromString($userId1),
            'email' => 'user1@example.com',
        ]);

        ClinicUserFactory::createOne([
            'id'    => Uuid::fromString($userId2),
            'email' => 'user2@example.com',
        ]);

        ClinicEntityFactory::createOne([
            'id'   => Uuid::fromString($clinicId),
            'name' => 'Test Clinic',
            'slug' => 'test-clinic',
        ]);

        ClinicMembershipEntityFactory::createOne([
            'userId'    => Uuid::fromString($userId1),
            'clinicId'  => Uuid::fromString($clinicId),
            'createdAt' => new \DateTimeImmutable('2024-01-01 10:00:00'),
        ]);

        ClinicMembershipEntityFactory::createOne([
            'userId'    => Uuid::fromString($userId2),
            'clinicId'  => Uuid::fromString($clinicId),
            'createdAt' => new \DateTimeImmutable('2024-01-03 10:00:00'),
        ]);

        $result = $this->repository->listAll();

        self::assertCount(2, $result->memberships);
        self::assertSame(2, $result->total);
        self::assertSame('user2@example.com', $result->memberships[0]->userEmail);
        self::assertSame('user1@example.com', $result->memberships[1]->userEmail);
    }

    public function testListAllFiltersByClinicId(): void
    {
        $userId    = '11111111-1111-1111-1111-111111111111';
        $clinicId1 = '22222222-2222-2222-2222-222222222222';
        $clinicId2 = '33333333-3333-3333-3333-333333333333';

        ClinicUserFactory::createOne([
            'id'    => Uuid::fromString($userId),
            'email' => 'user@example.com',
        ]);

        ClinicEntityFactory::createOne([
            'id'   => Uuid::fromString($clinicId1),
            'name' => 'Clinic 1',
            'slug' => 'clinic-1',
        ]);

        ClinicEntityFactory::createOne([
            'id'   => Uuid::fromString($clinicId2),
            'name' => 'Clinic 2',
            'slug' => 'clinic-2',
        ]);

        ClinicMembershipEntityFactory::createOne([
            'userId'   => Uuid::fromString($userId),
            'clinicId' => Uuid::fromString($clinicId1),
        ]);

        ClinicMembershipEntityFactory::createOne([
            'userId'   => Uuid::fromString($userId),
            'clinicId' => Uuid::fromString($clinicId2),
        ]);

        $result = $this->repository->listAll(clinicId: $clinicId1);

        self::assertCount(1, $result->memberships);
        self::assertSame($clinicId1, $result->memberships[0]->clinicId);
        self::assertSame('Clinic 1', $result->memberships[0]->clinicName);
    }

    public function testListAllFiltersByUserId(): void
    {
        $userId1  = '11111111-1111-1111-1111-111111111111';
        $userId2  = '22222222-2222-2222-2222-222222222222';
        $clinicId = '33333333-3333-3333-3333-333333333333';

        ClinicUserFactory::createOne([
            'id'    => Uuid::fromString($userId1),
            'email' => 'user1@example.com',
        ]);

        ClinicUserFactory::createOne([
            'id'    => Uuid::fromString($userId2),
            'email' => 'user2@example.com',
        ]);

        ClinicEntityFactory::createOne([
            'id'   => Uuid::fromString($clinicId),
            'name' => 'Test Clinic',
            'slug' => 'test-clinic',
        ]);

        ClinicMembershipEntityFactory::createOne([
            'userId'   => Uuid::fromString($userId1),
            'clinicId' => Uuid::fromString($clinicId),
        ]);

        ClinicMembershipEntityFactory::createOne([
            'userId'   => Uuid::fromString($userId2),
            'clinicId' => Uuid::fromString($clinicId),
        ]);

        $result = $this->repository->listAll(userId: $userId1);

        self::assertCount(1, $result->memberships);
        self::assertSame($userId1, $result->memberships[0]->userId);
        self::assertSame('user1@example.com', $result->memberships[0]->userEmail);
    }

    public function testListAllFiltersByStatus(): void
    {
        $userId    = '11111111-1111-1111-1111-111111111111';
        $clinicId1 = '22222222-2222-2222-2222-222222222222';
        $clinicId2 = '33333333-3333-3333-3333-333333333333';

        ClinicUserFactory::createOne([
            'id'    => Uuid::fromString($userId),
            'email' => 'user@example.com',
        ]);

        ClinicEntityFactory::createOne([
            'id'   => Uuid::fromString($clinicId1),
            'name' => 'Test Clinic 1',
            'slug' => 'test-clinic-1',
        ]);

        ClinicEntityFactory::createOne([
            'id'   => Uuid::fromString($clinicId2),
            'name' => 'Test Clinic 2',
            'slug' => 'test-clinic-2',
        ]);

        ClinicMembershipEntityFactory::createOne([
            'userId'   => Uuid::fromString($userId),
            'clinicId' => Uuid::fromString($clinicId1),
            'status'   => ClinicMembershipStatus::ACTIVE,
        ]);

        ClinicMembershipEntityFactory::createOne([
            'userId'   => Uuid::fromString($userId),
            'clinicId' => Uuid::fromString($clinicId2),
            'status'   => ClinicMembershipStatus::DISABLED,
        ]);

        $result = $this->repository->listAll(status: ClinicMembershipStatus::ACTIVE);

        self::assertCount(1, $result->memberships);
        self::assertSame(ClinicMembershipStatus::ACTIVE, $result->memberships[0]->status);
    }

    public function testListAllFiltersByRole(): void
    {
        $userId    = '11111111-1111-1111-1111-111111111111';
        $clinicId1 = '22222222-2222-2222-2222-222222222222';
        $clinicId2 = '33333333-3333-3333-3333-333333333333';

        ClinicUserFactory::createOne([
            'id'    => Uuid::fromString($userId),
            'email' => 'user@example.com',
        ]);

        ClinicEntityFactory::createOne([
            'id'   => Uuid::fromString($clinicId1),
            'name' => 'Test Clinic 1',
            'slug' => 'test-clinic-1',
        ]);

        ClinicEntityFactory::createOne([
            'id'   => Uuid::fromString($clinicId2),
            'name' => 'Test Clinic 2',
            'slug' => 'test-clinic-2',
        ]);

        ClinicMembershipEntityFactory::createOne([
            'userId'   => Uuid::fromString($userId),
            'clinicId' => Uuid::fromString($clinicId1),
            'role'     => ClinicMemberRole::VETERINARY,
        ]);

        ClinicMembershipEntityFactory::createOne([
            'userId'   => Uuid::fromString($userId),
            'clinicId' => Uuid::fromString($clinicId2),
            'role'     => ClinicMemberRole::CLINIC_ADMIN,
        ]);

        $result = $this->repository->listAll(role: ClinicMemberRole::VETERINARY);

        self::assertCount(1, $result->memberships);
        self::assertSame(ClinicMemberRole::VETERINARY, $result->memberships[0]->role);
    }

    public function testListAllFiltersByEngagement(): void
    {
        $userId    = '11111111-1111-1111-1111-111111111111';
        $clinicId1 = '22222222-2222-2222-2222-222222222222';
        $clinicId2 = '33333333-3333-3333-3333-333333333333';

        ClinicUserFactory::createOne([
            'id'    => Uuid::fromString($userId),
            'email' => 'user@example.com',
        ]);

        ClinicEntityFactory::createOne([
            'id'   => Uuid::fromString($clinicId1),
            'name' => 'Test Clinic 1',
            'slug' => 'test-clinic-1',
        ]);

        ClinicEntityFactory::createOne([
            'id'   => Uuid::fromString($clinicId2),
            'name' => 'Test Clinic 2',
            'slug' => 'test-clinic-2',
        ]);

        ClinicMembershipEntityFactory::createOne([
            'userId'     => Uuid::fromString($userId),
            'clinicId'   => Uuid::fromString($clinicId1),
            'engagement' => ClinicMembershipEngagement::EMPLOYEE,
        ]);

        ClinicMembershipEntityFactory::createOne([
            'userId'     => Uuid::fromString($userId),
            'clinicId'   => Uuid::fromString($clinicId2),
            'engagement' => ClinicMembershipEngagement::CONTRACTOR,
        ]);

        $result = $this->repository->listAll(engagement: ClinicMembershipEngagement::EMPLOYEE);

        self::assertCount(1, $result->memberships);
        self::assertSame(ClinicMembershipEngagement::EMPLOYEE, $result->memberships[0]->engagement);
    }

    public function testListAllCombinesMultipleFilters(): void
    {
        $userId    = '11111111-1111-1111-1111-111111111111';
        $clinicId1 = '22222222-2222-2222-2222-222222222222';
        $clinicId2 = '33333333-3333-3333-3333-333333333333';

        ClinicUserFactory::createOne([
            'id'    => Uuid::fromString($userId),
            'email' => 'user@example.com',
        ]);

        ClinicEntityFactory::createOne([
            'id'   => Uuid::fromString($clinicId1),
            'name' => 'Test Clinic 1',
            'slug' => 'test-clinic-1',
        ]);

        ClinicEntityFactory::createOne([
            'id'   => Uuid::fromString($clinicId2),
            'name' => 'Test Clinic 2',
            'slug' => 'test-clinic-2',
        ]);

        ClinicMembershipEntityFactory::createOne([
            'userId'     => Uuid::fromString($userId),
            'clinicId'   => Uuid::fromString($clinicId1),
            'role'       => ClinicMemberRole::VETERINARY,
            'engagement' => ClinicMembershipEngagement::EMPLOYEE,
            'status'     => ClinicMembershipStatus::ACTIVE,
        ]);

        ClinicMembershipEntityFactory::createOne([
            'userId'     => Uuid::fromString($userId),
            'clinicId'   => Uuid::fromString($clinicId2),
            'role'       => ClinicMemberRole::CLINIC_ADMIN,
            'engagement' => ClinicMembershipEngagement::CONTRACTOR,
            'status'     => ClinicMembershipStatus::DISABLED,
        ]);

        $result = $this->repository->listAll(
            clinicId: $clinicId1,
            userId: $userId,
            status: ClinicMembershipStatus::ACTIVE,
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
        );

        self::assertCount(1, $result->memberships);
        self::assertSame($clinicId1, $result->memberships[0]->clinicId);
        self::assertSame($userId, $result->memberships[0]->userId);
        self::assertSame(ClinicMemberRole::VETERINARY, $result->memberships[0]->role);
        self::assertSame(ClinicMembershipEngagement::EMPLOYEE, $result->memberships[0]->engagement);
        self::assertSame(ClinicMembershipStatus::ACTIVE, $result->memberships[0]->status);
    }

    public function testListAllPreservesAllFieldsIncludingDates(): void
    {
        $membershipId = '11111111-1111-1111-1111-111111111111';
        $userId       = '22222222-2222-2222-2222-222222222222';
        $clinicId     = '33333333-3333-3333-3333-333333333333';

        $validFrom  = new \DateTimeImmutable('2024-01-01 10:00:00');
        $validUntil = new \DateTimeImmutable('2025-12-31 23:59:59');
        $createdAt  = new \DateTimeImmutable('2023-12-01 08:30:00');

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
            'id'         => Uuid::fromString($membershipId),
            'userId'     => Uuid::fromString($userId),
            'clinicId'   => Uuid::fromString($clinicId),
            'role'       => ClinicMemberRole::CLINIC_ADMIN,
            'engagement' => ClinicMembershipEngagement::CONTRACTOR,
            'status'     => ClinicMembershipStatus::DISABLED,
            'validFrom'  => $validFrom,
            'validUntil' => $validUntil,
            'createdAt'  => $createdAt,
        ]);

        $result = $this->repository->listAll();

        self::assertCount(1, $result->memberships);
        $item = $result->memberships[0];

        self::assertSame($membershipId, $item->membershipId);
        self::assertSame($clinicId, $item->clinicId);
        self::assertSame('Test Clinic', $item->clinicName);
        self::assertSame($userId, $item->userId);
        self::assertSame('user@example.com', $item->userEmail);
        self::assertSame(ClinicMemberRole::CLINIC_ADMIN, $item->role);
        self::assertSame(ClinicMembershipEngagement::CONTRACTOR, $item->engagement);
        self::assertSame(ClinicMembershipStatus::DISABLED, $item->status);
        self::assertSame($validFrom->format('Y-m-d H:i:s'), $item->validFrom->format('Y-m-d H:i:s'));
        self::assertNotNull($item->validUntil);
        self::assertSame($validUntil->format('Y-m-d H:i:s'), $item->validUntil->format('Y-m-d H:i:s'));
        self::assertSame($createdAt->format('Y-m-d H:i:s'), $item->createdAt->format('Y-m-d H:i:s'));
    }

    public function testListAllHandlesNullValidUntil(): void
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
            'engagement' => ClinicMembershipEngagement::EMPLOYEE,
            'validFrom'  => new \DateTimeImmutable('2024-01-01'),
            'validUntil' => null,
        ]);

        $result = $this->repository->listAll();

        self::assertCount(1, $result->memberships);
        self::assertNull($result->memberships[0]->validUntil);
    }
}
