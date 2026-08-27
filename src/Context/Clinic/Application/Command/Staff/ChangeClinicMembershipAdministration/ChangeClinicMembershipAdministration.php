<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipAdministration;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Put the clinic administration hat on a membership, or take it off.
 *
 * The target value is carried explicitly rather than toggled: a caller that
 * cannot say which way it means would be a caller that can revoke by accident.
 */
final readonly class ChangeClinicMembershipAdministration implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $membershipId,
        public bool $isClinicAdministrator,
    ) {
    }
}
