<?php

declare(strict_types=1);

namespace App\Fixtures\System\AccessControl\Story;

use App\Context\Clinic\Domain\Staff\StaffRolePermissionMap;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Fixtures\System\AccessControl\Factory\RolePermissionEntityFactory;
use Zenstruck\Foundry\Story;

final class RolePermissionSeedStory extends Story
{
    public function build(): void
    {
        foreach (ClinicMemberRole::cases() as $role) {
            $permissions = StaffRolePermissionMap::permissionsFor($role);

            foreach ($permissions as $permission) {
                RolePermissionEntityFactory::new()
                    ->withRoleKey($role->value)
                    ->withPermission($permission->value)
                    ->create()
                ;
            }
        }
    }
}
