<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Staff\ListAllMemberships;

final readonly class MembershipCollection
{
    /**
     * @param list<MembershipListItem> $memberships
     */
    public function __construct(
        public array $memberships,
        public int $total,
    ) {
    }
}
