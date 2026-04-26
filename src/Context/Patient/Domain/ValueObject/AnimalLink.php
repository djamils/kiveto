<?php

declare(strict_types=1);

namespace App\Context\Patient\Domain\ValueObject;

final class AnimalLink
{
    private string $animalId;

    private function __construct(string $animalId)
    {
        $this->animalId = $animalId;
    }

    public static function fromAnimalId(string $animalId): self
    {
        return new self($animalId);
    }

    public function animalId(): string
    {
        return $this->animalId;
    }

    public function equals(self $other): bool
    {
        return $this->animalId === $other->animalId;
    }
}
