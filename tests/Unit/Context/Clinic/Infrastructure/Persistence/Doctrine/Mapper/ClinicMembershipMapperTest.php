<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Clinic\Domain\Staff\ClinicMembership;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipStatus;
use App\Context\Clinic\Domain\Staff\ValueObject\UserId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Entity\ClinicMembershipEntity;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Mapper\ClinicMembershipMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ClinicMembershipMapperTest extends TestCase
{
    private ClinicMembershipMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ClinicMembershipMapper();
    }

    public function testToDomainReconstitutesAggregate(): void
    {
        $entity = new ClinicMembershipEntity();
        $entity->setId(Uuid::fromString('01912345-6789-7abc-8def-0123456789ab'));
        $entity->setClinicId(Uuid::fromString('01912345-6789-7abc-8def-0123456789ac'));
        $entity->setUserId(Uuid::fromString('01912345-6789-7abc-8def-0123456789ad'));
        $entity->setRole(ClinicMemberRole::VETERINARY);
        $entity->setEngagement(ClinicMembershipEngagement::EMPLOYEE);
        $entity->setStatus(ClinicMembershipStatus::ACTIVE);
        $entity->setValidFrom(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $entity->setValidUntil(new \DateTimeImmutable('2026-12-31T23:59:59Z'));
        $entity->setCreatedAt(new \DateTimeImmutable('2026-01-01T00:00:00Z'));

        $domain = $this->mapper->toDomain($entity);

        self::assertSame('01912345-6789-7abc-8def-0123456789ab', $domain->id()->toString());
        self::assertSame('01912345-6789-7abc-8def-0123456789ac', $domain->clinicId()->toString());
        self::assertSame('01912345-6789-7abc-8def-0123456789ad', $domain->userId()->toString());
        self::assertSame(ClinicMemberRole::VETERINARY, $domain->role());
        self::assertSame(ClinicMembershipEngagement::EMPLOYEE, $domain->engagement());
        self::assertSame(ClinicMembershipStatus::ACTIVE, $domain->status());
        self::assertSame($entity->getValidFrom(), $domain->validFrom());
        self::assertSame($entity->getValidUntil(), $domain->validUntil());
        self::assertSame($entity->getCreatedAt(), $domain->createdAt());
    }

    public function testToDomainWithNullValidUntil(): void
    {
        $entity = new ClinicMembershipEntity();
        $entity->setId(Uuid::fromString('01912345-6789-7abc-8def-0123456789ab'));
        $entity->setClinicId(Uuid::fromString('01912345-6789-7abc-8def-0123456789ac'));
        $entity->setUserId(Uuid::fromString('01912345-6789-7abc-8def-0123456789ad'));
        $entity->setRole(ClinicMemberRole::MANAGER);
        $entity->setEngagement(ClinicMembershipEngagement::CONTRACTOR);
        $entity->setStatus(ClinicMembershipStatus::DISABLED);
        $entity->setValidFrom(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $entity->setValidUntil(null);
        $entity->setCreatedAt(new \DateTimeImmutable('2026-01-01T00:00:00Z'));

        $domain = $this->mapper->toDomain($entity);

        self::assertNull($domain->validUntil());
        self::assertSame(ClinicMemberRole::MANAGER, $domain->role());
        self::assertSame(ClinicMembershipEngagement::CONTRACTOR, $domain->engagement());
        self::assertSame(ClinicMembershipStatus::DISABLED, $domain->status());
    }

    public function testToEntityMapsAllProperties(): void
    {
        $membership = ClinicMembership::reconstitute(
            id: ClinicMembershipId::fromString('01912345-6789-7abc-8def-0123456789ab'),
            clinicId: ClinicId::fromString('01912345-6789-7abc-8def-0123456789ac'),
            userId: UserId::fromString('01912345-6789-7abc-8def-0123456789ad'),
            role: ClinicMemberRole::VETERINARY_ASSISTANT,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            status: ClinicMembershipStatus::ACTIVE,
            validFrom: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
            validUntil: new \DateTimeImmutable('2026-06-30T23:59:59Z'),
            createdAt: new \DateTimeImmutable('2026-01-01T00:00:00Z'),
        );

        $entity = $this->mapper->toEntity($membership);

        self::assertSame('01912345-6789-7abc-8def-0123456789ab', $entity->getId()->toRfc4122());
        self::assertSame('01912345-6789-7abc-8def-0123456789ac', $entity->getClinicId()->toRfc4122());
        self::assertSame('01912345-6789-7abc-8def-0123456789ad', $entity->getUserId()->toRfc4122());
        self::assertSame(ClinicMemberRole::VETERINARY_ASSISTANT, $entity->getRole());
        self::assertSame(ClinicMembershipEngagement::EMPLOYEE, $entity->getEngagement());
        self::assertSame(ClinicMembershipStatus::ACTIVE, $entity->getStatus());
        self::assertSame($membership->validFrom(), $entity->getValidFrom());
        self::assertSame($membership->validUntil(), $entity->getValidUntil());
        self::assertSame($membership->createdAt(), $entity->getCreatedAt());
    }

    public function testRoundTripSymmetry(): void
    {
        $original = ClinicMembership::reconstitute(
            id: ClinicMembershipId::fromString('01912345-6789-7abc-8def-0123456789ab'),
            clinicId: ClinicId::fromString('01912345-6789-7abc-8def-0123456789ac'),
            userId: UserId::fromString('01912345-6789-7abc-8def-0123456789ad'),
            role: ClinicMemberRole::RECEPTIONIST,
            engagement: ClinicMembershipEngagement::CONTRACTOR,
            status: ClinicMembershipStatus::ACTIVE,
            validFrom: new \DateTimeImmutable('2026-03-01T00:00:00Z'),
            validUntil: null,
            createdAt: new \DateTimeImmutable('2026-03-01T00:00:00Z'),
        );

        $reconstituted = $this->mapper->toDomain($this->mapper->toEntity($original));

        self::assertSame($original->id()->toString(), $reconstituted->id()->toString());
        self::assertSame($original->clinicId()->toString(), $reconstituted->clinicId()->toString());
        self::assertSame($original->userId()->toString(), $reconstituted->userId()->toString());
        self::assertSame($original->role(), $reconstituted->role());
        self::assertSame($original->engagement(), $reconstituted->engagement());
        self::assertSame($original->status(), $reconstituted->status());
        self::assertSame($original->validFrom(), $reconstituted->validFrom());
        self::assertSame($original->validUntil(), $reconstituted->validUntil());
        self::assertSame($original->createdAt(), $reconstituted->createdAt());
    }
}
