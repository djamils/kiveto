<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\Entity;

use App\System\PharmaceuticalRegistry\Domain\ValueObject\EuPackIdentifier;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\Gtin;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\Packaging;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PrescriptionRequirement;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PresentationDescription;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\PresentationId;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\UnitCount;

final class Presentation
{
    private PresentationId $id;
    private PresentationDescription $description;
    private ?UnitCount $unitCount;
    private ?Packaging $packaging;
    private ?Gtin $gtin;
    private ?EuPackIdentifier $euPackIdentifier;
    private PrescriptionRequirement $prescriptionRequirement;

    private function __construct(
        PresentationId $id,
        PresentationDescription $description,
        ?UnitCount $unitCount,
        ?Packaging $packaging,
        ?Gtin $gtin,
        ?EuPackIdentifier $euPackIdentifier,
        PrescriptionRequirement $prescriptionRequirement,
    ) {
        $this->id                      = $id;
        $this->description             = $description;
        $this->unitCount               = $unitCount;
        $this->packaging               = $packaging;
        $this->gtin                    = $gtin;
        $this->euPackIdentifier        = $euPackIdentifier;
        $this->prescriptionRequirement = $prescriptionRequirement;
    }

    public static function create(
        PresentationId $id,
        PresentationDescription $description,
        ?UnitCount $unitCount,
        ?Packaging $packaging,
        ?Gtin $gtin,
        ?EuPackIdentifier $euPackIdentifier,
        PrescriptionRequirement $prescriptionRequirement,
    ): self {
        return new self($id, $description, $unitCount, $packaging, $gtin, $euPackIdentifier, $prescriptionRequirement);
    }

    public function id(): PresentationId
    {
        return $this->id;
    }

    public function description(): PresentationDescription
    {
        return $this->description;
    }

    public function unitCount(): ?UnitCount
    {
        return $this->unitCount;
    }

    public function packaging(): ?Packaging
    {
        return $this->packaging;
    }

    public function gtin(): ?Gtin
    {
        return $this->gtin;
    }

    public function euPackIdentifier(): ?EuPackIdentifier
    {
        return $this->euPackIdentifier;
    }

    public function prescriptionRequirement(): PrescriptionRequirement
    {
        return $this->prescriptionRequirement;
    }

    public function withDescription(PresentationDescription $description): self
    {
        $clone              = clone $this;
        $clone->description = $description;

        return $clone;
    }

    public function withGtin(?Gtin $gtin): self
    {
        $clone       = clone $this;
        $clone->gtin = $gtin;

        return $clone;
    }

    public function withPrescriptionRequirement(PrescriptionRequirement $requirement): self
    {
        $clone                          = clone $this;
        $clone->prescriptionRequirement = $requirement;

        return $clone;
    }

    public function equals(self $other): bool
    {
        return $this->id->equals($other->id);
    }
}
