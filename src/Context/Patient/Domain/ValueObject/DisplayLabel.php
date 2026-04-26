<?php

declare(strict_types=1);

namespace App\Context\Patient\Domain\ValueObject;

final class DisplayLabel
{
    private DisplayLabelKind $kind;
    private string $value;

    private function __construct(DisplayLabelKind $kind, string $value)
    {
        $this->kind  = $kind;
        $this->value = $value;
    }

    public static function provisional(string $value): self
    {
        return new self(DisplayLabelKind::Provisional, $value);
    }

    public static function fromAnimal(string $animalName): self
    {
        return new self(DisplayLabelKind::FromAnimal, $animalName);
    }

    public static function custom(string $value): self
    {
        return new self(DisplayLabelKind::Custom, $value);
    }

    public function kind(): DisplayLabelKind
    {
        return $this->kind;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->kind === $other->kind
            && $this->value === $other->value;
    }
}
