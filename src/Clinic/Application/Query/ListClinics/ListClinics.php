<?php

declare(strict_types=1);

namespace App\Clinic\Application\Query\ListClinics;

use App\Clinic\Domain\ValueObject\ClinicStatus;
use App\Shared\Application\Bus\QueryInterface;

final readonly class ListClinics implements QueryInterface
{
    public function __construct(
        public ?ClinicStatus $status = null,
        public ?string $clinicGroupId = null,
        public ?string $search = null,
    ) {
    }
}
