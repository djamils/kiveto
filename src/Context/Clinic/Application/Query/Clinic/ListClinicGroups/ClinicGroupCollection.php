<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Clinic\ListClinicGroups;

use App\Context\Clinic\Application\Query\Clinic\GetClinicGroup\ClinicGroupDto;

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
