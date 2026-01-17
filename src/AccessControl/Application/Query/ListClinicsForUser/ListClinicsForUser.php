<?php

declare(strict_types=1);

namespace App\AccessControl\Application\Query\ListClinicsForUser;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ListClinicsForUser implements QueryInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
