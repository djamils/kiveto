<?php

declare(strict_types=1);

namespace App\Clinic\Application\Query\GetClinic;

use App\Clinic\Domain\ValueObject\ClinicStatus;

final readonly class ClinicDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public string $timeZone,
        public string $locale,
        public ClinicStatus $status,
        public ?string $clinicGroupId,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
