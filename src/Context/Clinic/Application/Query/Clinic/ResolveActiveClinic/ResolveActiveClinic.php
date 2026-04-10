<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Clinic\ResolveActiveClinic;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ResolveActiveClinic implements QueryInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
