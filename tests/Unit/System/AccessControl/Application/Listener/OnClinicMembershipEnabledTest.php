<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\AccessControl\Application\Listener;

use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipEnabled;
use App\System\AccessControl\Application\Listener\OnClinicMembershipEnabled;
use App\System\AccessControl\Domain\Repository\RoleAssignmentRepositoryInterface;
use App\System\AccessControl\Domain\RoleAssignment;
use PHPUnit\Framework\TestCase;

final class OnClinicMembershipEnabledTest extends TestCase
{
    public function testCreatesRoleAssignmentWithRole(): void
    {
        $repo = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (RoleAssignment $a): bool {
                return '01912345-6789-7abc-8def-000000000001' === $a->subjectId()->toString()
                    && '01912345-6789-7abc-8def-000000000002' === $a->tenantId()->toString()
                    && 'MANAGER' === $a->roleKey();
            }))
        ;

        $event = new ClinicMembershipEnabled(
            membershipId: '01912345-6789-7abc-8def-000000000003',
            clinicId: '01912345-6789-7abc-8def-000000000002',
            userId: '01912345-6789-7abc-8def-000000000001',
            role: 'MANAGER',
        );

        $listener = new OnClinicMembershipEnabled($repo);
        $listener($event);
    }
}
