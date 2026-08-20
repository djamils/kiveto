<?php

declare(strict_types=1);

namespace App\Context\Patient\Application\Query\GetPatientAnimalLink;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetPatientAnimalLink implements QueryInterface
{
    public function __construct(
        public string $patientId,
        public string $clinicId,
    ) {
    }
}
