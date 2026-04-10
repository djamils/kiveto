<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Query\Staff\ListAllMemberships;

use App\Context\Clinic\Application\Query\Staff\ListAllMemberships\MembershipCollection;
use App\Context\Clinic\Application\Query\Staff\ListAllMemberships\MembershipListItem;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipStatus;
use PHPUnit\Framework\TestCase;

final class MembershipCollectionTest extends TestCase
{
    public function testProperties(): void
    {
        $item = new MembershipListItem(
            membershipId: 'id',
            clinicId: 'clinic-id',
            clinicName: 'Paris',
            userId: 'user-id',
            userEmail: 'vet@kiveto.local',
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            status: ClinicMembershipStatus::ACTIVE,
            validFrom: new \DateTimeImmutable('2026-01-01'),
            validUntil: null,
            createdAt: new \DateTimeImmutable('2026-01-01'),
        );

        $collection = new MembershipCollection(memberships: [$item], total: 1);

        self::assertCount(1, $collection->memberships);
        self::assertSame(1, $collection->total);
        self::assertSame('id', $collection->memberships[0]->membershipId);
    }
}
