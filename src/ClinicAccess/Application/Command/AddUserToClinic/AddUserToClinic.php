<?php

declare(strict_types=1);

namespace App\ClinicAccess\Application\Command\AddUserToClinic;

use App\ClinicAccess\Domain\ValueObject\ClinicMemberRole;
use App\ClinicAccess\Domain\ValueObject\ClinicMembershipEngagement;

final readonly class AddUserToClinic
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
