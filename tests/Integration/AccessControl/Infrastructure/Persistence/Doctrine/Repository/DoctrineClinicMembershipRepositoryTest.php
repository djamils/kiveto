<?php

declare(strict_types=1);

namespace App\Tests\Integration\AccessControl\Infrastructure\Persistence\Doctrine\Repository;

use App\AccessControl\Domain\ClinicMembership;
use App\AccessControl\Domain\Repository\ClinicMembershipRepositoryInterface;
use App\AccessControl\Domain\ValueObject\ClinicId;
use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use App\AccessControl\Domain\ValueObject\ClinicMembershipStatus;
use App\AccessControl\Domain\ValueObject\MembershipId;
use App\AccessControl\Domain\ValueObject\UserId;
use App\Fixtures\AccessControl\Factory\ClinicMembershipEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineClinicMembershipRepositoryTest extends KernelTestCase
{
    use Factories;

    private ClinicMembershipRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $repository = static::getContainer()->get(ClinicMembershipRepositoryInterface::class);
        \assert($repository instanceof ClinicMembershipRepositoryInterface);
        $this->repository = $repository;
    }

    public function testSaveCreatesNewMembership(): void
    {
        $membership = ClinicMembership::create(
            id: MembershipId::fromString('11111111-1111-1111-1111-111111111111'),
            clinicId: ClinicId::fromString('22222222-2222-2222-2222-222222222222'),
            userId: UserId::fromString('33333333-3333-3333-3333-333333333333'),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: new \DateTimeImmutable('2024-01-01'),
            validUntil: null,
            createdAt: new \DateTimeImmutable('2024-01-01'),
        );

        $this->repository->save($membership);

        $found = $this->repository->findById(MembershipId::fromString('11111111-1111-1111-1111-111111111111'));

        self::assertNotNull($found);
        self::assertSame('11111111-1111-1111-1111-111111111111', $found->id()->toString());
        self::assertSame(ClinicMemberRole::VETERINARY, $found->role());
    }

    public function testSaveUpdatesExistingMembership(): void
    {
        $membershipId = '11111111-1111-1111-1111-111111111111';

        ClinicMembershipEntityFactory::createOne([
            'id'         => Uuid::fromString($membershipId),
            'clinicId'   => Uuid::fromString('22222222-2222-2222-2222-222222222222'),
            'userId'     => Uuid::fromString('33333333-3333-3333-3333-333333333333'),
            'role'       => ClinicMemberRole::VETERINARY,
            'engagement' => ClinicMembershipEngagement::EMPLOYEE,
            'status'     => ClinicMembershipStatus::ACTIVE,
        ]);

        $membership = $this->repository->findById(MembershipId::fromString($membershipId));
        self::assertNotNull($membership);

        $membership->changeRole(ClinicMemberRole::CLINIC_ADMIN);
        $membership->disable();

        $this->repository->save($membership);

        $updated = $this->repository->findById(MembershipId::fromString($membershipId));

        self::assertNotNull($updated);
        self::assertSame(ClinicMemberRole::CLINIC_ADMIN, $updated->role());
        self::assertSame(ClinicMembershipStatus::DISABLED, $updated->status());
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->findById(MembershipId::fromString('99999999-9999-9999-9999-999999999999'));

        self::assertNull($result);
    }

    public function testFindByIdReturnsMembership(): void
    {
        $membershipId = '11111111-1111-1111-1111-111111111111';

        ClinicMembershipEntityFactory::createOne([
            'id'       => Uuid::fromString($membershipId),
            'clinicId' => Uuid::fromString('22222222-2222-2222-2222-222222222222'),
            'userId'   => Uuid::fromString('33333333-3333-3333-3333-333333333333'),
            'role'     => ClinicMemberRole::ASSISTANT_VETERINARY,
        ]);

        $membership = $this->repository->findById(MembershipId::fromString($membershipId));

        self::assertNotNull($membership);
        self::assertSame($membershipId, $membership->id()->toString());
        self::assertSame(ClinicMemberRole::ASSISTANT_VETERINARY, $membership->role());
    }

    public function testFindByClinicAndUserReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->findByClinicAndUser(
            ClinicId::fromString('11111111-1111-1111-1111-111111111111'),
            UserId::fromString('22222222-2222-2222-2222-222222222222'),
        );

        self::assertNull($result);
    }

    public function testFindByClinicAndUserReturnsMembership(): void
    {
        $clinicId = '11111111-1111-1111-1111-111111111111';
        $userId   = '22222222-2222-2222-2222-222222222222';

        ClinicMembershipEntityFactory::createOne([
            'clinicId' => Uuid::fromString($clinicId),
            'userId'   => Uuid::fromString($userId),
            'role'     => ClinicMemberRole::VETERINARY,
        ]);

        $membership = $this->repository->findByClinicAndUser(
            ClinicId::fromString($clinicId),
            UserId::fromString($userId),
        );

        self::assertNotNull($membership);
        self::assertSame($clinicId, $membership->clinicId()->toString());
        self::assertSame($userId, $membership->userId()->toString());
    }

    public function testExistsByClinicAndUserReturnsFalseWhenNotFound(): void
    {
        $exists = $this->repository->existsByClinicAndUser(
            ClinicId::fromString('11111111-1111-1111-1111-111111111111'),
            UserId::fromString('22222222-2222-2222-2222-222222222222'),
        );

        self::assertFalse($exists);
    }

    public function testExistsByClinicAndUserReturnsTrueWhenFound(): void
    {
        $clinicId = '11111111-1111-1111-1111-111111111111';
        $userId   = '22222222-2222-2222-2222-222222222222';

        ClinicMembershipEntityFactory::createOne([
            'clinicId' => Uuid::fromString($clinicId),
            'userId'   => Uuid::fromString($userId),
        ]);

        $exists = $this->repository->existsByClinicAndUser(
            ClinicId::fromString($clinicId),
            UserId::fromString($userId),
        );

        self::assertTrue($exists);
    }

    public function testSaveWithNullValidUntil(): void
    {
        $membership = ClinicMembership::create(
            id: MembershipId::fromString('11111111-1111-1111-1111-111111111111'),
            clinicId: ClinicId::fromString('22222222-2222-2222-2222-222222222222'),
            userId: UserId::fromString('33333333-3333-3333-3333-333333333333'),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: new \DateTimeImmutable('2024-01-01'),
            validUntil: null,
            createdAt: new \DateTimeImmutable('2024-01-01'),
        );

        $this->repository->save($membership);

        $found = $this->repository->findById(MembershipId::fromString('11111111-1111-1111-1111-111111111111'));

        self::assertNotNull($found);
        self::assertNull($found->validUntil());
    }

    public function testFindByIdPreservesAllData(): void
    {
        $validFrom  = new \DateTimeImmutable('2024-01-01 10:00:00');
        $validUntil = new \DateTimeImmutable('2025-12-31 23:59:59');
        $createdAt  = new \DateTimeImmutable('2023-12-01 08:30:00');

        ClinicMembershipEntityFactory::createOne([
            'id'         => Uuid::fromString('11111111-1111-1111-1111-111111111111'),
            'clinicId'   => Uuid::fromString('22222222-2222-2222-2222-222222222222'),
            'userId'     => Uuid::fromString('33333333-3333-3333-3333-333333333333'),
            'role'       => ClinicMemberRole::CLINIC_ADMIN,
            'engagement' => ClinicMembershipEngagement::CONTRACTOR,
            'status'     => ClinicMembershipStatus::DISABLED,
            'validFrom'  => $validFrom,
            'validUntil' => $validUntil,
            'createdAt'  => $createdAt,
        ]);

        $membership = $this->repository->findById(MembershipId::fromString('11111111-1111-1111-1111-111111111111'));

        self::assertNotNull($membership);
        self::assertSame('11111111-1111-1111-1111-111111111111', $membership->id()->toString());
        self::assertSame('22222222-2222-2222-2222-222222222222', $membership->clinicId()->toString());
        self::assertSame('33333333-3333-3333-3333-333333333333', $membership->userId()->toString());
        self::assertSame(ClinicMemberRole::CLINIC_ADMIN, $membership->role());
        self::assertSame(ClinicMembershipEngagement::CONTRACTOR, $membership->engagement());
        self::assertSame(ClinicMembershipStatus::DISABLED, $membership->status());
        self::assertSame($validFrom->format('Y-m-d H:i:s'), $membership->validFrom()->format('Y-m-d H:i:s'));
        self::assertNotNull($membership->validUntil());
        self::assertSame($validUntil->format('Y-m-d H:i:s'), $membership->validUntil()->format('Y-m-d H:i:s'));
        self::assertSame($createdAt->format('Y-m-d H:i:s'), $membership->createdAt()->format('Y-m-d H:i:s'));
    }
}
