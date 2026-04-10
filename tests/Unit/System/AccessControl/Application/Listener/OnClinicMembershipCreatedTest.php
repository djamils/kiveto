<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\AccessControl\Application\Listener;

use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipCreated;
use App\System\AccessControl\Application\Listener\OnClinicMembershipCreated;
use App\System\AccessControl\Domain\Repository\RoleAssignmentRepositoryInterface;
use App\System\AccessControl\Domain\RoleAssignment;
use PHPUnit\Framework\TestCase;

final class OnClinicMembershipCreatedTest extends TestCase
{
    public function testCreatesRoleAssignment(): void
    {
        $repo = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (RoleAssignment $a): bool {
                return '01912345-6789-7abc-8def-000000000001' === $a->subjectId()->toString()
                    && '01912345-6789-7abc-8def-000000000002' === $a->tenantId()->toString()
                    && 'VETERINARY' === $a->roleKey();
            }))
        ;

        $event = new ClinicMembershipCreated(
            membershipId: '01912345-6789-7abc-8def-000000000003',
            clinicId: '01912345-6789-7abc-8def-000000000002',
            userId: '01912345-6789-7abc-8def-000000000001',
            role: 'VETERINARY',
            engagement: 'EMPLOYEE',
            validFrom: '2026-01-01T00:00:00+00:00',
            validUntil: null,
        );

        $listener = new OnClinicMembershipCreated($repo);
        $listener($event);
    }
}
