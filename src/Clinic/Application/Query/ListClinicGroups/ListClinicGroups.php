<?php

declare(strict_types=1);

namespace App\Clinic\Application\Query\ListClinicGroups;

use App\Clinic\Domain\ValueObject\ClinicGroupStatus;

final readonly class ListClinicGroups
{
    public function __construct(
        public ?ClinicGroupStatus $status = null,
    ) {
    }
}
