<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Query\Staff\GetStaffPermissionMatrix;

use App\Context\Clinic\Application\Query\Staff\GetStaffPermissionMatrix\GetStaffPermissionMatrix;
use App\Context\Clinic\Application\Query\Staff\GetStaffPermissionMatrix\GetStaffPermissionMatrixHandler;
use App\Context\Clinic\Domain\Staff\ClinicPermission;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use PHPUnit\Framework\TestCase;

final class GetStaffPermissionMatrixHandlerTest extends TestCase
{
    public function testReadsTheRoleMapAndTheHat(): void
    {
        $matrix = (new GetStaffPermissionMatrixHandler())(new GetStaffPermissionMatrix());

        self::assertSame(array_map(static fn (ClinicPermission $p): string => $p->value, ClinicPermission::cases()), $matrix->permissions);
        self::assertSame(array_map(static fn (ClinicMemberRole $r): string => $r->value, ClinicMemberRole::cases()), array_keys($matrix->roles));
        self::assertSame(['manage_staff', 'manage_billing'], $matrix->administrator);

        // Prescribing is a veterinarian's, never the reception's.
        self::assertContains('create_prescription', $matrix->roles['VETERINARY']);
        self::assertNotContains('create_prescription', $matrix->roles['RECEPTIONIST']);

        // No role manages the clinic by itself: only the hat does.
        foreach ($matrix->roles as $permissions) {
            self::assertNotContains('manage_staff', $permissions);
        }
    }
}
