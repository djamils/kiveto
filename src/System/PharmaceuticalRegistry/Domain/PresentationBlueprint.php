<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain;

use App\System\PharmaceuticalRegistry\Domain\ValueObject\EuPackIdentifier;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\Gtin;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\Packaging;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PrescriptionRequirement;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PresentationDescription;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\UnitCount;

final readonly class PresentationBlueprint
{
    public function __construct(
        public PresentationDescription $description,
        public ?Gtin $gtin,
        public PrescriptionRequirement $prescriptionRequirement,
        public ?UnitCount $unitCount,
        public ?Packaging $packaging,
        public ?EuPackIdentifier $euPackIdentifier,
    ) {
    }
}
