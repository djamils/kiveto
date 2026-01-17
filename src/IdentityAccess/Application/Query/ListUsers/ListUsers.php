<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Query\ListUsers;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ListUsers implements QueryInterface
{
    public function __construct(
        public ?string $search = null,
        public ?string $type = null,
        public ?string $status = null,
    ) {
    }
}
