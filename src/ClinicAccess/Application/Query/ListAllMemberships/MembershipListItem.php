<?php

declare(strict_types=1);

namespace App\ClinicAccess\Application\Query\ListAllMemberships;

use App\ClinicAccess\Domain\ValueObject\ClinicMemberRole;
use App\ClinicAccess\Domain\ValueObject\ClinicMembershipEngagement;
use App\ClinicAccess\Domain\ValueObject\ClinicMembershipStatus;

final readonly class MembershipListItem
{
    public function __construct(
        public string $membershipId,
        public string $clinicId,
        public string $clinicName,
        public string $userId,
        public string $userEmail,
        public ClinicMemberRole $role,
        public ClinicMembershipEngagement $engagement,
        public ClinicMembershipStatus $status,
        public \DateTimeImmutable $validFrom,
        public ?\DateTimeImmutable $validUntil,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
