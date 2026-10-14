<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Twig;

use App\Presentation\Clinic\Security\ClinicPermissionGuard;

/**
 * Permission checks for the shell templates, so the navigation never offers
 * a door that would answer 403.
 */
final readonly class ClinicSidebarRuntime
{
    public function __construct(
        private ClinicPermissionGuard $guard,
    ) {
    }

    /**
     * Whether the member may do something in the clinic they are in.
     */
    public function can(string $permission): bool
    {
        return $this->guard->has($permission);
    }
}
