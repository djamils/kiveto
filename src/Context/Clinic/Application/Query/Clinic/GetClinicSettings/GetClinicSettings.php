<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Clinic\GetClinicSettings;

use App\Shared\Application\Bus\RequestCacheableQueryInterface;

/**
 * Everything the settings pages put in their forms, in one read.
 */
final readonly class GetClinicSettings implements RequestCacheableQueryInterface
{
    public function __construct(
        public string $clinicId,
    ) {
    }
}
