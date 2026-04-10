<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\Staff;

use App\Context\Clinic\Domain\Staff\ClinicPermission;
use App\Context\Clinic\Domain\Staff\StaffRolePermissionMap;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use PHPUnit\Framework\TestCase;

final class StaffRolePermissionMapTest extends TestCase
{
    public function testManagerHasAllPermissions(): void
    {
        $permissions = StaffRolePermissionMap::permissionsFor(ClinicMemberRole::MANAGER);

        self::assertCount(9, $permissions);
        self::assertContains(ClinicPermission::MANAGE_STAFF, $permissions);
        self::assertContains(ClinicPermission::MANAGE_BILLING, $permissions);
        self::assertContains(ClinicPermission::CREATE_PRESCRIPTION, $permissions);
    }

    public function testVeterinaryHasClinicalPermissions(): void
    {
        $permissions = StaffRolePermissionMap::permissionsFor(ClinicMemberRole::VETERINARY);

        self::assertCount(7, $permissions);
        self::assertContains(ClinicPermission::CREATE_PRESCRIPTION, $permissions);
        self::assertContains(ClinicPermission::CREATE_CONSULTATION, $permissions);
        self::assertNotContains(ClinicPermission::MANAGE_STAFF, $permissions);
        self::assertNotContains(ClinicPermission::MANAGE_BILLING, $permissions);
    }

    public function testVeterinaryAssistantHasNoCreatePermissions(): void
    {
        $permissions = StaffRolePermissionMap::permissionsFor(ClinicMemberRole::VETERINARY_ASSISTANT);

        self::assertCount(5, $permissions);
        self::assertContains(ClinicPermission::VIEW_MEDICAL_RECORD, $permissions);
        self::assertNotContains(ClinicPermission::CREATE_PRESCRIPTION, $permissions);
        self::assertNotContains(ClinicPermission::CREATE_CONSULTATION, $permissions);
    }

    public function testReceptionistHasMinimalPermissions(): void
    {
        $permissions = StaffRolePermissionMap::permissionsFor(ClinicMemberRole::RECEPTIONIST);

        self::assertCount(4, $permissions);
        self::assertContains(ClinicPermission::VIEW_DASHBOARD, $permissions);
        self::assertContains(ClinicPermission::MANAGE_APPOINTMENTS, $permissions);
        self::assertNotContains(ClinicPermission::VIEW_MEDICAL_RECORD, $permissions);
    }

    public function testAllRoleCasesAreCovered(): void
    {
        foreach (ClinicMemberRole::cases() as $role) {
            $permissions = StaffRolePermissionMap::permissionsFor($role);
            self::assertNotEmpty($permissions, \sprintf('Role %s has no permissions', $role->value));
        }
    }

    public function testEveryPermissionIsMappedToAtLeastOneRole(): void
    {
        $allMapped = [];
        foreach (ClinicMemberRole::cases() as $role) {
            foreach (StaffRolePermissionMap::permissionsFor($role) as $p) {
                $allMapped[$p->value] = true;
            }
        }

        foreach (ClinicPermission::cases() as $permission) {
            self::assertArrayHasKey(
                $permission->value,
                $allMapped,
                \sprintf('Permission %s is not mapped to any role', $permission->value),
            );
        }
    }
}
