<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Query\Staff\ListAllMemberships;

use App\Context\Clinic\Application\Query\Staff\ListAllMemberships\MembershipListItem;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipStatus;
use PHPUnit\Framework\TestCase;

final class MembershipListItemTest extends TestCase
{
    public function testProperties(): void
    {
        $validFrom = new \DateTimeImmutable('2026-01-01');
        $createdAt = new \DateTimeImmutable('2026-01-01');

        $item = new MembershipListItem(
            membershipId: 'membership-uuid',
            clinicId: 'clinic-uuid',
            clinicName: 'Paris',
            userId: 'user-uuid',
            userEmail: 'vet@kiveto.local',
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            status: ClinicMembershipStatus::ACTIVE,
            validFrom: $validFrom,
            validUntil: null,
            createdAt: $createdAt,
        );

        self::assertSame('membership-uuid', $item->membershipId);
        self::assertSame('clinic-uuid', $item->clinicId);
        self::assertSame('Paris', $item->clinicName);
        self::assertSame('user-uuid', $item->userId);
        self::assertSame('vet@kiveto.local', $item->userEmail);
        self::assertSame(ClinicMemberRole::VETERINARY, $item->role);
        self::assertSame(ClinicMembershipEngagement::EMPLOYEE, $item->engagement);
        self::assertSame(ClinicMembershipStatus::ACTIVE, $item->status);
        self::assertSame($validFrom, $item->validFrom);
        self::assertNull($item->validUntil);
        self::assertSame($createdAt, $item->createdAt);
    }
}
