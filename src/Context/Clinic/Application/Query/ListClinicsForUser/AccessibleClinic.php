<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\ListClinicsForUser;

use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;

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
