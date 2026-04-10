<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Staff\ListAllMemberships;

use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipStatus;

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
