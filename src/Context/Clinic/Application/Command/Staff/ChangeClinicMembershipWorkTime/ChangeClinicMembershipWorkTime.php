<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipWorkTime;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ChangeClinicMembershipWorkTime implements CommandInterface
{
    public function __construct(
        public string $membershipId,
        public int $workTimePercent,
    ) {
    }
}
