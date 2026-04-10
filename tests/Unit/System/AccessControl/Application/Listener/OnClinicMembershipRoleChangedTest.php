<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\AccessControl\Application\Listener;

use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipRoleChanged;
use App\System\AccessControl\Application\Listener\OnClinicMembershipRoleChanged;
use App\System\AccessControl\Domain\Repository\RoleAssignmentRepositoryInterface;
use App\System\AccessControl\Domain\RoleAssignment;
use App\System\AccessControl\Domain\ValueObject\SubjectId;
use App\System\AccessControl\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class OnClinicMembershipRoleChangedTest extends TestCase
{
    public function testUpdatesRoleKey(): void
    {
        $existing = RoleAssignment::create(
            SubjectId::fromString('01912345-6789-7abc-8def-000000000001'),
            TenantId::fromString('01912345-6789-7abc-8def-000000000002'),
            'VETERINARY',
        );

        $repo = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('findBySubjectAndTenant')
            ->willReturn($existing)
        ;
        $repo->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (RoleAssignment $a): bool => 'MANAGER' === $a->roleKey()))
        ;

        $event = new ClinicMembershipRoleChanged(
            membershipId: '01912345-6789-7abc-8def-000000000003',
            clinicId: '01912345-6789-7abc-8def-000000000002',
            userId: '01912345-6789-7abc-8def-000000000001',
            newRole: 'MANAGER',
        );

        $listener = new OnClinicMembershipRoleChanged($repo);
        $listener($event);
    }

    public function testDoesNothingWhenAssignmentNotFound(): void
    {
        $repo = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('findBySubjectAndTenant')
            ->willReturn(null)
        ;
        $repo->expects(self::never())->method('save');

        $event = new ClinicMembershipRoleChanged(
            membershipId: '01912345-6789-7abc-8def-000000000003',
            clinicId: '01912345-6789-7abc-8def-000000000002',
            userId: '01912345-6789-7abc-8def-000000000001',
            newRole: 'MANAGER',
        );

        $listener = new OnClinicMembershipRoleChanged($repo);
        $listener($event);
    }
}
