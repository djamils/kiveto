<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Exception;

final class CannotCreatePractitionerMembershipWithoutProfile extends \RuntimeException
{
    public function __construct(string $role)
    {
        parent::__construct(\sprintf(
            'Cannot create a membership with role "%s" via CreateClinicMembership.'
                . ' Use OnboardStaffMember to create practitioner memberships with a linked StaffProfile.',
            $role,
        ));
    }
}
