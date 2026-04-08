<?php

declare(strict_types=1);

namespace App\System\AccessControl\Application\Query\GetUserMembershipInClinic;

use App\System\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\System\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use App\System\AccessControl\Domain\ValueObject\ClinicMembershipStatus;

final readonly class MembershipDetails
{
    public function __construct(
        public string $membershipId,
        public ClinicMemberRole $role,
        public ClinicMembershipEngagement $engagement,
        public ClinicMembershipStatus $status,
        public \DateTimeImmutable $validFrom,
        public ?\DateTimeImmutable $validUntil,
        public bool $isEffectiveNow,
    ) {
    }
}
