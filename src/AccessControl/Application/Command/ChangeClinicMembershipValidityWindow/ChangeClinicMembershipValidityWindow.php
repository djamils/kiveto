<?php

declare(strict_types=1);

namespace App\AccessControl\Application\Command\ChangeClinicMembershipValidityWindow;

final readonly class ChangeClinicMembershipValidityWindow
{
    public function __construct(
        public string $membershipId,
        public \DateTimeImmutable $validFrom,
        public ?\DateTimeImmutable $validUntil,
    ) {
    }
}
