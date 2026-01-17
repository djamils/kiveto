<?php

declare(strict_types=1);

namespace App\AccessControl\Application\Command\ChangeClinicMembershipEngagement;

use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use App\Shared\Application\Bus\CommandInterface;

final readonly class ChangeClinicMembershipEngagement implements CommandInterface
{
    public function __construct(
        public string $membershipId,
        public ClinicMembershipEngagement $engagement,
    ) {
    }
}
