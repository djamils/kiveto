<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Clinic\ListClinics;

use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
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
