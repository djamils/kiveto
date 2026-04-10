<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Staff\CreateClinicMembership;

use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
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
