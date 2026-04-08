<?php

declare(strict_types=1);

namespace App\System\AccessControl\Application\Command\ChangeClinicMembershipEngagement;

use App\Shared\Application\Bus\CommandInterface;
use App\System\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;

final readonly class ChangeClinicMembershipEngagement implements CommandInterface
{
    public function __construct(
        public string $membershipId,
        public ClinicMembershipEngagement $engagement,
    ) {
    }
}
