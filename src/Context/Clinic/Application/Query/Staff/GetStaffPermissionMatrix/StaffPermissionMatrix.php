<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Staff\GetStaffPermissionMatrix;

final readonly class StaffPermissionMatrix
{
    /**
     * @param list<string>                $permissions   Every permission slug, in declaration order
     * @param array<string, list<string>> $roles         Role value → permission slugs it grants
     * @param list<string>                $administrator Permission slugs the hat adds to any role
     */
    public function __construct(
        public array $permissions,
        public array $roles,
        public array $administrator,
    ) {
    }
}
