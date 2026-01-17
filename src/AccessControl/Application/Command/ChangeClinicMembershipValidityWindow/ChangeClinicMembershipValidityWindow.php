<?php

declare(strict_types=1);

namespace App\AccessControl\Application\Command\ChangeClinicMembershipValidityWindow;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ChangeClinicMembershipValidityWindow implements CommandInterface
{
    public function __construct(
        public string $membershipId,
        public \DateTimeImmutable $validFrom,
        public ?\DateTimeImmutable $validUntil,
    ) {
    }
}
