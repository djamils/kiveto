<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Staff\GetUserMembershipInClinic;

use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipStatus;

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
