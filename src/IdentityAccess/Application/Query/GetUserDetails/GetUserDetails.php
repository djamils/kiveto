<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\GetUserDetails;

readonly class GetUserDetails
{
    public function __construct(public string $userId)
    {
    }
}
