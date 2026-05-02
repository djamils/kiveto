<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Clinic\GetClinic;

use App\Context\Clinic\Domain\ValueObject\ClinicStatus;

final readonly class ClinicDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public string $timeZone,
        public string $locale,
        public string $countryCode,
        public ?string $jurisdictionCode,
        public string $currencyCode,
        public ClinicStatus $status,
        public ?string $clinicGroupId,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }
}
