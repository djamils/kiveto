<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\GetPresentationDetail;

readonly class PresentationView
{
    public function __construct(
        public string $id,
        public string $marketingAuthorizationId,
        public string $description,
        public ?string $gtin,
        public ?string $unitCount,
        public ?string $packaging,
        public bool $prescriptionRequired,
        public string $prescriptionClass,
    ) {
    }
}
