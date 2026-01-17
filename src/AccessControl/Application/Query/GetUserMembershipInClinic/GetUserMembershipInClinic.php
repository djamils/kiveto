<?php

declare(strict_types=1);

namespace App\AccessControl\Application\Query\GetUserMembershipInClinic;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetUserMembershipInClinic implements QueryInterface
{
    public function __construct(
        public string $userId,
        public string $clinicId,
    ) {
    }
}
