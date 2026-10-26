<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Staff\ListClinicMembersForNotification;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Who in a clinic should be told (bell): the user ids of the memberships in
 * force now, optionally of one role, optionally only the administrators.
 */
final readonly class ListClinicMembersForNotification implements QueryInterface
{
    /**
     * @param string|null $role a ClinicMemberRole value (VETERINARY_ASSISTANT…), null for every role
     */
    public function __construct(
        public string $clinicId,
        public ?string $role = null,
        public bool $administratorsOnly = false,
    ) {
    }
}
