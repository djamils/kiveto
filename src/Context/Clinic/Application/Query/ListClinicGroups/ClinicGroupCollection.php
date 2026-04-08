<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\ListClinicGroups;

use App\Context\Clinic\Application\Query\GetClinicGroup\ClinicGroupDto;

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
