<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

final class PrescriptionRequirement
{
    private bool $isRequired;
    private PrescriptionClass $prescriptionClass;
    private ?PrescriptionRetention $retention;
    private ?JurisdictionalPrescriptionCode $jurisdictionalCode;

    private function __construct(
        bool $isRequired,
        PrescriptionClass $prescriptionClass,
        ?PrescriptionRetention $retention,
        ?JurisdictionalPrescriptionCode $jurisdictionalCode,
    ) {
        $this->isRequired         = $isRequired;
        $this->prescriptionClass  = $prescriptionClass;
        $this->retention          = $retention;
        $this->jurisdictionalCode = $jurisdictionalCode;
    }

    public static function none(): self
    {
        return new self(false, PrescriptionClass::NONE, null, null);
    }

    public static function rx(PrescriptionClass $class, JurisdictionalPrescriptionCode $jurisdictionalCode): self
    {
        return new self(true, $class, null, $jurisdictionalCode);
    }

    public static function rxWithRetention(
        PrescriptionClass $class,
        JurisdictionalPrescriptionCode $jurisdictionalCode,
        int $years,
    ): self {
        return new self(true, $class, PrescriptionRetention::ofYears($years), $jurisdictionalCode);
    }

    /** Hardcodes PrescriptionClass::RX_NARCOTIC — semantic intent for narcotic prescriptions. */
    public static function narcotic(JurisdictionalPrescriptionCode $jurisdictionalCode): self
    {
        return new self(true, PrescriptionClass::RX_NARCOTIC, null, $jurisdictionalCode);
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function prescriptionClass(): PrescriptionClass
    {
        return $this->prescriptionClass;
    }

    public function retention(): ?PrescriptionRetention
    {
        return $this->retention;
    }

    public function jurisdictionalCode(): ?JurisdictionalPrescriptionCode
    {
        return $this->jurisdictionalCode;
    }

    public function equals(self $other): bool
    {
        if ($this->isRequired !== $other->isRequired || $this->prescriptionClass !== $other->prescriptionClass) {
            return false;
        }

        if (null === $this->retention xor null === $other->retention) {
            return false;
        }

        if (null !== $this->retention && null !== $other->retention && !$this->retention->equals($other->retention)) {
            return false;
        }

        if (null === $this->jurisdictionalCode xor null === $other->jurisdictionalCode) {
            return false;
        }

        $bothNonNullAndDiffer = null !== $this->jurisdictionalCode
            && null !== $other->jurisdictionalCode
            && !$this->jurisdictionalCode->equals($other->jurisdictionalCode);

        if ($bothNonNullAndDiffer) {
            return false;
        }

        return true;
    }
}
