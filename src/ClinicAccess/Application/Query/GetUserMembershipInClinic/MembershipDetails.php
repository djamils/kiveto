<?php

declare(strict_types=1);

namespace App\ClinicAccess\Application\Query\GetUserMembershipInClinic;

use App\ClinicAccess\Domain\ValueObject\ClinicMemberRole;
use App\ClinicAccess\Domain\ValueObject\ClinicMembershipEngagement;
use App\ClinicAccess\Domain\ValueObject\ClinicMembershipStatus;

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
