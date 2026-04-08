<?php

declare(strict_types=1);

namespace App\System\AccessControl\Application\Command\CreateClinicMembership;

use App\Shared\Application\Bus\CommandInterface;
use App\System\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\System\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;

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
