<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Clinic\GetClinic;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetClinic implements QueryInterface
{
    public function __construct(
        public string $clinicId,
    ) {
    }
}
