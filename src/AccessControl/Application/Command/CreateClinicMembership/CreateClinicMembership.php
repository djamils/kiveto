<?php

declare(strict_types=1);

namespace App\AccessControl\Application\Command\CreateClinicMembership;

use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;

final readonly class CreateClinicMembership
{
    public function __construct(
        public string $clinicId,
        public string $userId,
        public ClinicMemberRole $role,
        public ClinicMembershipEngagement $engagement,
        public ?\DateTimeImmutable $validFrom = null,
        public ?\DateTimeImmutable $validUntil = null,
    ) {
    }
}
