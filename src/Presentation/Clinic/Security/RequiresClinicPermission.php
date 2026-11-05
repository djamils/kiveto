<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Security;

/**
 * The permission a clinic controller requires, in place of the rule of its
 * area (ClinicRoutePermissions): a write that is not the area's usual one,
 * such as the vitals of a consultation or a credit note at the desk.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class RequiresClinicPermission
{
    /**
     * @param string $permission one of the ClinicPermissionGuard constants
     */
    public function __construct(public string $permission)
    {
    }
}
