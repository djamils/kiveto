<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Staff\GetStaffPermissionMatrix;

use App\Context\Clinic\Domain\Staff\ClinicPermission;
use App\Context\Clinic\Domain\Staff\StaffRolePermissionMap;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Reads the static role map so a page can show it without reaching into the
 * domain. The map is fixed by the product, so this is a read, never a setting.
 */
#[AsMessageHandler]
final readonly class GetStaffPermissionMatrixHandler
{
    public function __invoke(GetStaffPermissionMatrix $query): StaffPermissionMatrix
    {
        $roles = [];

        foreach (ClinicMemberRole::cases() as $role) {
            $roles[$role->value] = self::slugs(StaffRolePermissionMap::permissionsFor($role));
        }

        return new StaffPermissionMatrix(
            permissions: self::slugs(ClinicPermission::cases()),
            roles: $roles,
            administrator: self::slugs(StaffRolePermissionMap::administratorPermissions()),
        );
    }

    /**
     * @param list<ClinicPermission> $permissions
     *
     * @return list<string>
     */
    private static function slugs(array $permissions): array
    {
        return array_map(static fn (ClinicPermission $permission): string => $permission->value, $permissions);
    }
}
