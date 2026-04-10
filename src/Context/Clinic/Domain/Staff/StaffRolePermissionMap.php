<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Staff;

use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;

final class StaffRolePermissionMap
{
    /** @var array<string, list<ClinicPermission>>|null */
    private static ?array $map = null;

    /**
     * @return list<ClinicPermission>
     */
    public static function permissionsFor(ClinicMemberRole $role): array
    {
        return self::buildMap()[$role->value];
    }

    /**
     * @return array<string, list<ClinicPermission>>
     */
    private static function buildMap(): array
    {
        if (null !== self::$map) {
            return self::$map;
        }

        self::$map = [
            ClinicMemberRole::MANAGER->value => [
                ClinicPermission::VIEW_DASHBOARD,
                ClinicPermission::MANAGE_STAFF,
                ClinicPermission::MANAGE_BILLING,
                ClinicPermission::MANAGE_APPOINTMENTS,
                ClinicPermission::VIEW_MEDICAL_RECORD,
                ClinicPermission::CREATE_PRESCRIPTION,
                ClinicPermission::CREATE_CONSULTATION,
                ClinicPermission::MANAGE_CLIENTS,
                ClinicPermission::MANAGE_ANIMALS,
            ],
            ClinicMemberRole::VETERINARY->value => [
                ClinicPermission::VIEW_DASHBOARD,
                ClinicPermission::MANAGE_APPOINTMENTS,
                ClinicPermission::VIEW_MEDICAL_RECORD,
                ClinicPermission::CREATE_PRESCRIPTION,
                ClinicPermission::CREATE_CONSULTATION,
                ClinicPermission::MANAGE_CLIENTS,
                ClinicPermission::MANAGE_ANIMALS,
            ],
            ClinicMemberRole::VETERINARY_ASSISTANT->value => [
                ClinicPermission::VIEW_DASHBOARD,
                ClinicPermission::MANAGE_APPOINTMENTS,
                ClinicPermission::VIEW_MEDICAL_RECORD,
                ClinicPermission::MANAGE_CLIENTS,
                ClinicPermission::MANAGE_ANIMALS,
            ],
            ClinicMemberRole::RECEPTIONIST->value => [
                ClinicPermission::VIEW_DASHBOARD,
                ClinicPermission::MANAGE_APPOINTMENTS,
                ClinicPermission::MANAGE_CLIENTS,
                ClinicPermission::MANAGE_ANIMALS,
            ],
        ];

        return self::$map;
    }
}
