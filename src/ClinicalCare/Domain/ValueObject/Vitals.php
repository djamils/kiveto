<?php

declare(strict_types=1);

namespace App\ClinicalCare\Domain\ValueObject;

final readonly class Vitals
{
    private function __construct(
        private ?float $weightKg,
        private ?float $temperatureC,
    ) {
        if (null !== $weightKg && $weightKg <= 0) {
            throw new \InvalidArgumentException('Weight must be positive');
        }

        if (null !== $temperatureC && ($temperatureC < 30 || $temperatureC > 45)) {
            throw new \InvalidArgumentException('Temperature must be between 30 and 45°C');
        }
    }

    public static function create(?float $weightKg, ?float $temperatureC = null): self
    {
        if (null === $weightKg && null === $temperatureC) {
            throw new \InvalidArgumentException('At least one vital sign must be provided');
        }

        return new self($weightKg, $temperatureC);
    }

    public function getWeightKg(): ?float
    {
        return $this->weightKg;
    }

    public function getTemperatureC(): ?float
    {
        return $this->temperatureC;
    }

    public function hasWeight(): bool
    {
        return null !== $this->weightKg;
    }

    public function hasTemperature(): bool
    {
        return null !== $this->temperatureC;
    }
}
