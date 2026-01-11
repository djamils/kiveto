<?php

declare(strict_types=1);

namespace App\AccessControl\Application\Command\ChangeClinicMembershipValidity;

final readonly class ChangeClinicMembershipValidity
{
    public function __construct(
        public string $membershipId,
        public \DateTimeImmutable $validFrom,
        public ?\DateTimeImmutable $validUntil,
    ) {
    }
}
