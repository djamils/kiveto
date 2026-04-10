<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Clinic\ListClinicsForUser;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ListClinicsForUser implements QueryInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
