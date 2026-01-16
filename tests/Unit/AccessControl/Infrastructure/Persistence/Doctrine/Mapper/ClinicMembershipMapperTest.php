<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Infrastructure\Persistence\Doctrine\Mapper;

use App\AccessControl\Domain\ClinicMembership;
use App\AccessControl\Domain\ValueObject\ClinicId;
use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use App\AccessControl\Domain\ValueObject\ClinicMembershipStatus;
use App\AccessControl\Domain\ValueObject\MembershipId;
use App\AccessControl\Domain\ValueObject\UserId;
use App\AccessControl\Infrastructure\Persistence\Doctrine\Entity\ClinicMembershipEntity;
use App\AccessControl\Infrastructure\Persistence\Doctrine\Mapper\ClinicMembershipMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ClinicMembershipMapperTest extends TestCase
{
    private ClinicMembershipMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ClinicMembershipMapper();
    }

    public function testToDomainMapsEntityToAggregate(): void
    {
        $entity = new ClinicMembershipEntity();
        $entity->setId(Uuid::fromString('11111111-1111-1111-1111-111111111111'));
        $entity->setClinicId(Uuid::fromString('22222222-2222-2222-2222-222222222222'));
        $entity->setUserId(Uuid::fromString('33333333-3333-3333-3333-333333333333'));
        $entity->setRole(ClinicMemberRole::VETERINARY);
        $entity->setEngagement(ClinicMembershipEngagement::EMPLOYEE);
        $entity->setStatus(ClinicMembershipStatus::ACTIVE);
        $entity->setValidFrom(new \DateTimeImmutable('2024-01-01'));
        $entity->setValidUntil(new \DateTimeImmutable('2025-01-01'));
        $entity->setCreatedAt(new \DateTimeImmutable('2024-01-01'));

        $membership = $this->mapper->toDomain($entity);

        self::assertInstanceOf(ClinicMembership::class, $membership);
        self::assertSame('11111111-1111-1111-1111-111111111111', $membership->id()->toString());
        self::assertSame('22222222-2222-2222-2222-222222222222', $membership->clinicId()->toString());
        self::assertSame('33333333-3333-3333-3333-333333333333', $membership->userId()->toString());
        self::assertSame(ClinicMemberRole::VETERINARY, $membership->role());
        self::assertSame(ClinicMembershipEngagement::EMPLOYEE, $membership->engagement());
        self::assertSame(ClinicMembershipStatus::ACTIVE, $membership->status());
    }

    public function testToDomainWithDisabledStatus(): void
    {
        $entity = new ClinicMembershipEntity();
        $entity->setId(Uuid::v7());
        $entity->setClinicId(Uuid::v7());
        $entity->setUserId(Uuid::v7());
        $entity->setRole(ClinicMemberRole::CLINIC_ADMIN);
        $entity->setEngagement(ClinicMembershipEngagement::CONTRACTOR);
        $entity->setStatus(ClinicMembershipStatus::DISABLED);
        $entity->setValidFrom(new \DateTimeImmutable('2024-01-01'));
        $entity->setValidUntil(null);
        $entity->setCreatedAt(new \DateTimeImmutable('2024-01-01'));

        $membership = $this->mapper->toDomain($entity);

        self::assertSame(ClinicMembershipStatus::DISABLED, $membership->status());
    }

    public function testToDomainWithNullValidUntil(): void
    {
        $entity = new ClinicMembershipEntity();
        $entity->setId(Uuid::v7());
        $entity->setClinicId(Uuid::v7());
        $entity->setUserId(Uuid::v7());
        $entity->setRole(ClinicMemberRole::VETERINARY);
        $entity->setEngagement(ClinicMembershipEngagement::EMPLOYEE);
        $entity->setStatus(ClinicMembershipStatus::ACTIVE);
        $entity->setValidFrom(new \DateTimeImmutable('2024-01-01'));
        $entity->setValidUntil(null);
        $entity->setCreatedAt(new \DateTimeImmutable('2024-01-01'));

        $membership = $this->mapper->toDomain($entity);

        self::assertNull($membership->validUntil());
    }

    public function testToDomainDoesNotEmitEvents(): void
    {
        $entity = new ClinicMembershipEntity();
        $entity->setId(Uuid::v7());
        $entity->setClinicId(Uuid::v7());
        $entity->setUserId(Uuid::v7());
        $entity->setRole(ClinicMemberRole::VETERINARY);
        $entity->setEngagement(ClinicMembershipEngagement::EMPLOYEE);
        $entity->setStatus(ClinicMembershipStatus::ACTIVE);
        $entity->setValidFrom(new \DateTimeImmutable('2024-01-01'));
        $entity->setValidUntil(null);
        $entity->setCreatedAt(new \DateTimeImmutable('2024-01-01'));

        $membership = $this->mapper->toDomain($entity);

        $events = $membership->recordedDomainEvents();
        self::assertCount(0, $events);
    }

    public function testToEntityMapsAggregateToEntity(): void
    {
        $membership = ClinicMembership::create(
            id: MembershipId::fromString('11111111-1111-1111-1111-111111111111'),
            clinicId: ClinicId::fromString('22222222-2222-2222-2222-222222222222'),
            userId: UserId::fromString('33333333-3333-3333-3333-333333333333'),
            role: ClinicMemberRole::CLINIC_ADMIN,
            engagement: ClinicMembershipEngagement::CONTRACTOR,
            validFrom: new \DateTimeImmutable('2024-01-01'),
            validUntil: new \DateTimeImmutable('2025-01-01'),
            createdAt: new \DateTimeImmutable('2024-01-01'),
        );

        $entity = $this->mapper->toEntity($membership);

        self::assertInstanceOf(ClinicMembershipEntity::class, $entity);
        self::assertSame('11111111-1111-1111-1111-111111111111', $entity->getId()->toRfc4122());
        self::assertSame('22222222-2222-2222-2222-222222222222', $entity->getClinicId()->toRfc4122());
        self::assertSame('33333333-3333-3333-3333-333333333333', $entity->getUserId()->toRfc4122());
        self::assertSame(ClinicMemberRole::CLINIC_ADMIN, $entity->getRole());
        self::assertSame(ClinicMembershipEngagement::CONTRACTOR, $entity->getEngagement());
        self::assertSame(ClinicMembershipStatus::ACTIVE, $entity->getStatus());
    }

    public function testToEntityWithNullValidUntil(): void
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

        $entity = $this->mapper->toEntity($membership);

        self::assertNull($entity->getValidUntil());
    }

    public function testRoundTripConversion(): void
    {
        $originalMembership = ClinicMembership::create(
            id: MembershipId::fromString('11111111-1111-1111-1111-111111111111'),
            clinicId: ClinicId::fromString('22222222-2222-2222-2222-222222222222'),
            userId: UserId::fromString('33333333-3333-3333-3333-333333333333'),
            role: ClinicMemberRole::ASSISTANT_VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: new \DateTimeImmutable('2024-01-01'),
            validUntil: new \DateTimeImmutable('2025-12-31'),
            createdAt: new \DateTimeImmutable('2024-01-01'),
        );

        $entity                  = $this->mapper->toEntity($originalMembership);
        $reconstructedMembership = $this->mapper->toDomain($entity);

        self::assertSame($originalMembership->id()->toString(), $reconstructedMembership->id()->toString());
        self::assertSame($originalMembership->clinicId()->toString(), $reconstructedMembership->clinicId()->toString());
        self::assertSame($originalMembership->userId()->toString(), $reconstructedMembership->userId()->toString());
        self::assertSame($originalMembership->role(), $reconstructedMembership->role());
        self::assertSame($originalMembership->engagement(), $reconstructedMembership->engagement());
        self::assertSame($originalMembership->status(), $reconstructedMembership->status());
    }
}
