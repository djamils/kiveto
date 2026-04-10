<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Staff\GetUserMembershipInClinic;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetUserMembershipInClinic implements QueryInterface
{
    public function __construct(
        public string $userId,
        public string $clinicId,
    ) {
    }
}
