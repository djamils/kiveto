<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Clinic\GetClinic;

use App\Shared\Application\Bus\RequestCacheableQueryInterface;

final readonly class GetClinic implements RequestCacheableQueryInterface
{
    public function __construct(
        public string $clinicId,
    ) {
    }
}
