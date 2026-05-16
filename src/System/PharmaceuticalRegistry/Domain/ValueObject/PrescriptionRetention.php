<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

final class PrescriptionRetention
{
    private int $durationYears;

    private function __construct(int $durationYears)
    {
        if ($durationYears <= 0) {
            throw new \InvalidArgumentException('Prescription retention duration must be greater than 0.');
        }

        $this->durationYears = $durationYears;
    }

    public static function ofYears(int $years): self
    {
        return new self($years);
    }

    public function durationYears(): int
    {
        return $this->durationYears;
    }

    public function equals(self $other): bool
    {
        return $this->durationYears === $other->durationYears;
    }
}
