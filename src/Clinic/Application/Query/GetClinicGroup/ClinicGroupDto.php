<?php

declare(strict_types=1);

namespace App\Clinic\Application\Query\GetClinicGroup;

use App\Clinic\Domain\ValueObject\ClinicGroupStatus;

final readonly class ClinicGroupDto
{
    public function __construct(
        public string $id,
        public string $name,
        public ClinicGroupStatus $status,
        public string $createdAt,
    ) {
    }
}
