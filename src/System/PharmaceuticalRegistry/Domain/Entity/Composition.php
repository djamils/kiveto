<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\Entity;

use App\System\PharmaceuticalRegistry\Domain\ValueObject\ActiveSubstanceId;

final class Composition
{
    private ActiveSubstanceId $activeSubstanceId;
    private ?string $quantityValue;
    private ?string $quantityUnitLabel;
    private ?string $quantityUnitCode;
    private bool $isExcipient;

    private function __construct(
        ActiveSubstanceId $activeSubstanceId,
        ?string $quantityValue,
        ?string $quantityUnitLabel,
        ?string $quantityUnitCode,
        bool $isExcipient,
    ) {
        if (null !== $quantityValue && is_numeric($quantityValue) && bccomp($quantityValue, '0') < 0) {
            throw new \InvalidArgumentException(\sprintf('Quantity value must be >= 0, got "%s".', $quantityValue));
        }

        $this->activeSubstanceId = $activeSubstanceId;
        $this->quantityValue     = $quantityValue;
        $this->quantityUnitLabel = $quantityUnitLabel;
        $this->quantityUnitCode  = $quantityUnitCode;
        $this->isExcipient       = $isExcipient;
    }

    public static function of(
        ActiveSubstanceId $activeSubstanceId,
        ?string $quantityValue,
        ?string $quantityUnitLabel,
        ?string $quantityUnitCode,
        bool $isExcipient,
    ): self {
        return new self($activeSubstanceId, $quantityValue, $quantityUnitLabel, $quantityUnitCode, $isExcipient);
    }

    public function activeSubstanceId(): ActiveSubstanceId
    {
        return $this->activeSubstanceId;
    }

    public function quantityValue(): ?string
    {
        return $this->quantityValue;
    }

    public function quantityUnitLabel(): ?string
    {
        return $this->quantityUnitLabel;
    }

    public function quantityUnitCode(): ?string
    {
        return $this->quantityUnitCode;
    }

    public function isExcipient(): bool
    {
        return $this->isExcipient;
    }
}
