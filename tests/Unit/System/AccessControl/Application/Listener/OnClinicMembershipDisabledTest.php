<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\AccessControl\Application\Listener;

use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipDisabled;
use App\System\AccessControl\Application\Listener\OnClinicMembershipDisabled;
use App\System\AccessControl\Domain\Repository\RoleAssignmentRepositoryInterface;
use App\System\AccessControl\Domain\ValueObject\SubjectId;
use App\System\AccessControl\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class OnClinicMembershipDisabledTest extends TestCase
{
    private const string USER_ID   = '01912345-6789-7abc-8def-000000000001';
    private const string CLINIC_ID = '01912345-6789-7abc-8def-000000000002';

    public function testDeletesRoleAssignment(): void
    {
        $repo = $this->createMock(RoleAssignmentRepositoryInterface::class);
        $repo->expects(self::once())
            ->method('deleteBySubjectAndTenant')
            ->with(
                self::callback(static fn (SubjectId $s): bool => self::USER_ID === $s->toString()),
                self::callback(static fn (TenantId $t): bool => self::CLINIC_ID === $t->toString()),
            )
        ;

        $event = new ClinicMembershipDisabled(
            membershipId: '01912345-6789-7abc-8def-000000000003',
            clinicId: self::CLINIC_ID,
            userId: self::USER_ID,
        );

        $listener = new OnClinicMembershipDisabled($repo);
        $listener($event);
    }
}
