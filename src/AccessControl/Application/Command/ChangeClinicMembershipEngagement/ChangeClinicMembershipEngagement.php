<?php

declare(strict_types=1);

namespace App\AccessControl\Application\Command\ChangeClinicMembershipEngagement;

use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;

final readonly class ChangeClinicMembershipEngagement
{
    public function __construct(
        public string $membershipId,
        public ClinicMembershipEngagement $engagement,
    ) {
    }
}
