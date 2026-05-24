<?php

declare(strict_types=1);

namespace App\Context\Catalog\Infrastructure\Adapter\HealthRegistry;

final readonly class PharmaceuticalRef
{
    public function __construct(
        public string $id,
        public string $commercialName,
        public ?string $gtin,
        public ?bool $requiresPrescription,
        public ?string $prescriptionClass,
        public ?bool $isControlledSubstance,
        public string $status,
    ) {
    }

    public function isMarketable(): bool
    {
        return 'WITHDRAWN' !== strtoupper($this->status);
    }
}
