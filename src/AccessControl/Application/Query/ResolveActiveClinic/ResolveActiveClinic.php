<?php

declare(strict_types=1);

namespace App\AccessControl\Application\Query\ResolveActiveClinic;

final readonly class ResolveActiveClinic
{
    public function __construct(
        public string $userId,
    ) {
    }
}
