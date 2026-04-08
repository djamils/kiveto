<?php

declare(strict_types=1);

namespace App\System\AccessControl\Application\Query\ListClinicsForUser;

use App\System\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\System\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;

final readonly class AccessibleClinic
{
    public function __construct(
        public string $clinicId,
        public string $clinicName,
        public string $clinicSlug,
        public string $clinicStatus,
        public ClinicMemberRole $memberRole,
        public ClinicMembershipEngagement $engagement,
        public \DateTimeImmutable $validFrom,
        public ?\DateTimeImmutable $validUntil,
    ) {
    }
}
