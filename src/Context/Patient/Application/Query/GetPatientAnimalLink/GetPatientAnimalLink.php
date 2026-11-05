<?php

declare(strict_types=1);

namespace App\Context\Patient\Application\Query\GetPatientAnimalLink;

use App\Shared\Application\Bus\RequestCacheableQueryInterface;

/** Read by several blocks of the consultation page: once for the request. */
final readonly class GetPatientAnimalLink implements RequestCacheableQueryInterface
{
    public function __construct(
        public string $patientId,
        public string $clinicId,
    ) {
    }
}
