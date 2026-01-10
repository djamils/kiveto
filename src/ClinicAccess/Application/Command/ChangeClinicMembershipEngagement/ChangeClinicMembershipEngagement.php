<?php

declare(strict_types=1);

namespace App\ClinicAccess\Application\Command\ChangeClinicMembershipEngagement;

use App\ClinicAccess\Domain\ValueObject\ClinicMembershipEngagement;

final readonly class ChangeClinicMembershipEngagement
{
    public function __construct(
        public string $membershipId,
        public ClinicMembershipEngagement $engagement,
    ) {
    }
}
