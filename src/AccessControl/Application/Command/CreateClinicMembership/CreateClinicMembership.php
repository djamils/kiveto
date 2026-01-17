<?php

declare(strict_types=1);

namespace App\AccessControl\Application\Command\CreateClinicMembership;

use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use App\Shared\Application\Bus\CommandInterface;

final readonly class CreateClinicMembership implements CommandInterface
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
