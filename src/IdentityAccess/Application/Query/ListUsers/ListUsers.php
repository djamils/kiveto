<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\ListUsers;

final readonly class ListUsers
{
    public function __construct(
        public ?string $search = null,
        public ?string $type = null,
        public ?string $status = null,
    ) {
    }
}
