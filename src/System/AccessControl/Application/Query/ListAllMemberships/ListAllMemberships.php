<?php

declare(strict_types=1);

namespace App\System\AccessControl\Application\Query\ListAllMemberships;

use App\Shared\Application\Bus\QueryInterface;
use App\System\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\System\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use App\System\AccessControl\Domain\ValueObject\ClinicMembershipStatus;

final readonly class ListAllMemberships implements QueryInterface
{
    public function __construct(
        public ?string $clinicId = null,
        public ?string $userId = null,
        public ?ClinicMembershipStatus $status = null,
        public ?ClinicMemberRole $role = null,
        public ?ClinicMembershipEngagement $engagement = null,
    ) {
    }
}
