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
     * Permissions granted by wearing the clinic administration hat, on top of
     * whatever the clinical role already grants. Kept out of the role map so
     * that the hat stays the single source of truth for clinic administration.
     *
     * @return list<ClinicPermission>
     */
    public static function administratorPermissions(): array
    {
        return [ClinicPermission::MANAGE_STAFF, ClinicPermission::MANAGE_BILLING];
    }

    /**
     * @return array<string, list<ClinicPermission>>
     */
    private static function buildMap(): array
    {
        if (null !== self::$map) {
            return self::$map;
        }

        // Every role collects payments; the assistant records the vitals but
        // writes nothing else in a consultation; the receptionist reads the
        // medical record without writing it, and does not manage the stock
        // (decided 2026-11-05). Credit notes, discounts, catalogue prices and
        // the billing settings stay with the administration hat.
        self::$map = [
            ClinicMemberRole::MANAGER->value => [
                ClinicPermission::VIEW_DASHBOARD,
                ClinicPermission::MANAGE_APPOINTMENTS,
                ClinicPermission::VIEW_MEDICAL_RECORD,
                ClinicPermission::CREATE_PRESCRIPTION,
                ClinicPermission::CREATE_CONSULTATION,
                ClinicPermission::RECORD_VITALS,
                ClinicPermission::MANAGE_CLIENTS,
                ClinicPermission::MANAGE_ANIMALS,
                ClinicPermission::COLLECT_PAYMENTS,
                ClinicPermission::MANAGE_STOCK,
            ],
            ClinicMemberRole::VETERINARY->value => [
                ClinicPermission::VIEW_DASHBOARD,
                ClinicPermission::MANAGE_APPOINTMENTS,
                ClinicPermission::VIEW_MEDICAL_RECORD,
                ClinicPermission::CREATE_PRESCRIPTION,
                ClinicPermission::CREATE_CONSULTATION,
                ClinicPermission::RECORD_VITALS,
                ClinicPermission::MANAGE_CLIENTS,
                ClinicPermission::MANAGE_ANIMALS,
                ClinicPermission::COLLECT_PAYMENTS,
                ClinicPermission::MANAGE_STOCK,
            ],
            ClinicMemberRole::VETERINARY_ASSISTANT->value => [
                ClinicPermission::VIEW_DASHBOARD,
                ClinicPermission::MANAGE_APPOINTMENTS,
                ClinicPermission::VIEW_MEDICAL_RECORD,
                ClinicPermission::RECORD_VITALS,
                ClinicPermission::MANAGE_CLIENTS,
                ClinicPermission::MANAGE_ANIMALS,
                ClinicPermission::COLLECT_PAYMENTS,
                ClinicPermission::MANAGE_STOCK,
            ],
            ClinicMemberRole::RECEPTIONIST->value => [
                ClinicPermission::VIEW_DASHBOARD,
                ClinicPermission::MANAGE_APPOINTMENTS,
                ClinicPermission::VIEW_MEDICAL_RECORD,
                ClinicPermission::MANAGE_CLIENTS,
                ClinicPermission::MANAGE_ANIMALS,
                ClinicPermission::COLLECT_PAYMENTS,
            ],
        ];

        return self::$map;
    }
}
