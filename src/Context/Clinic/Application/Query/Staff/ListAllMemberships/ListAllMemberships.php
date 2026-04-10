<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Staff\ListAllMemberships;

use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipStatus;
use App\Shared\Application\Bus\QueryInterface;

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
