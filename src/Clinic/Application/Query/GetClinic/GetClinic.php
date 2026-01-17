<?php

declare(strict_types=1);

namespace App\Clinic\Application\Query\GetClinic;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetClinic implements QueryInterface
{
    public function __construct(
        public string $clinicId,
    ) {
    }
}
