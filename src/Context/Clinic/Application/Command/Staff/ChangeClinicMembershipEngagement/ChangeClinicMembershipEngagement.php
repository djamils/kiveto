<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipEngagement;

use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Shared\Application\Bus\CommandInterface;

final readonly class ChangeClinicMembershipEngagement implements CommandInterface
{
    public function __construct(
        public string $membershipId,
        public ClinicMembershipEngagement $engagement,
    ) {
    }
}
