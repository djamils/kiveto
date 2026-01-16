<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Application\Query\ListAllMemberships;

use App\AccessControl\Application\Query\ListAllMemberships\MembershipListItem;
use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use App\AccessControl\Domain\ValueObject\ClinicMembershipStatus;
use PHPUnit\Framework\TestCase;

final class MembershipListItemTest extends TestCase
{
    public function testDtoConstructionWithAllProperties(): void
    {
        $validFrom  = new \DateTimeImmutable('2024-01-01');
        $validUntil = new \DateTimeImmutable('2025-01-01');
        $createdAt  = new \DateTimeImmutable('2023-12-01');

        $dto = new MembershipListItem(
            membershipId: '11111111-1111-1111-1111-111111111111',
            clinicId: '22222222-2222-2222-2222-222222222222',
            clinicName: 'Test Clinic',
            userId: '33333333-3333-3333-3333-333333333333',
            userEmail: 'vet@example.com',
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            status: ClinicMembershipStatus::ACTIVE,
            validFrom: $validFrom,
            validUntil: $validUntil,
            createdAt: $createdAt,
        );

        self::assertSame('11111111-1111-1111-1111-111111111111', $dto->membershipId);
        self::assertSame('22222222-2222-2222-2222-222222222222', $dto->clinicId);
        self::assertSame('Test Clinic', $dto->clinicName);
        self::assertSame('33333333-3333-3333-3333-333333333333', $dto->userId);
        self::assertSame('vet@example.com', $dto->userEmail);
        self::assertSame(ClinicMemberRole::VETERINARY, $dto->role);
        self::assertSame(ClinicMembershipEngagement::EMPLOYEE, $dto->engagement);
        self::assertSame(ClinicMembershipStatus::ACTIVE, $dto->status);
        self::assertSame($validFrom, $dto->validFrom);
        self::assertSame($validUntil, $dto->validUntil);
        self::assertSame($createdAt, $dto->createdAt);
    }

    public function testDtoWithNullValidUntil(): void
    {
        $dto = new MembershipListItem(
            membershipId: '11111111-1111-1111-1111-111111111111',
            clinicId: '22222222-2222-2222-2222-222222222222',
            clinicName: 'Test Clinic',
            userId: '33333333-3333-3333-3333-333333333333',
            userEmail: 'admin@example.com',
            role: ClinicMemberRole::CLINIC_ADMIN,
            engagement: ClinicMembershipEngagement::CONTRACTOR,
            status: ClinicMembershipStatus::ACTIVE,
            validFrom: new \DateTimeImmutable('2024-01-01'),
            validUntil: null,
            createdAt: new \DateTimeImmutable('2023-12-01'),
        );

        self::assertNull($dto->validUntil);
    }

    public function testDtoIsReadonly(): void
    {
        $dto = new MembershipListItem(
            membershipId: '44444444-4444-4444-4444-444444444444',
            clinicId: '55555555-5555-5555-5555-555555555555',
            clinicName: 'Another Clinic',
            userId: '66666666-6666-6666-6666-666666666666',
            userEmail: 'assistant@example.com',
            role: ClinicMemberRole::ASSISTANT_VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            status: ClinicMembershipStatus::DISABLED,
            validFrom: new \DateTimeImmutable('2024-01-01'),
            validUntil: new \DateTimeImmutable('2024-12-31'),
            createdAt: new \DateTimeImmutable('2023-12-01'),
        );

        self::assertSame('44444444-4444-4444-4444-444444444444', $dto->membershipId);
        self::assertSame('assistant@example.com', $dto->userEmail);
        self::assertSame(ClinicMembershipStatus::DISABLED, $dto->status);
    }
}
