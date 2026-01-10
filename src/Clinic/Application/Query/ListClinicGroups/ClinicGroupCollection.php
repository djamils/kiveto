<?php

declare(strict_types=1);

namespace App\Clinic\Application\Query\ListClinicGroups;

use App\Clinic\Application\Query\GetClinicGroup\ClinicGroupDto;

final readonly class ClinicGroupCollection
{
    /**
     * @param list<ClinicGroupDto> $clinicGroups
     */
    public function __construct(
        public array $clinicGroups,
        public int $total,
    ) {
    }
}
