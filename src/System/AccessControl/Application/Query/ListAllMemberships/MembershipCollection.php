<?php

declare(strict_types=1);

namespace App\System\AccessControl\Application\Query\ListAllMemberships;

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
