<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

final class ControlledSubstance
{
    private ControlledSubstanceClass $class;
    private JurisdictionalControlCode $jurisdictionalCode;
    private ?string $restrictions;

    private function __construct(
        ControlledSubstanceClass $class,
        JurisdictionalControlCode $jurisdictionalCode,
        ?string $restrictions,
    ) {
        $this->class              = $class;
        $this->jurisdictionalCode = $jurisdictionalCode;
        $this->restrictions       = $restrictions;
    }

    public static function of(
        ControlledSubstanceClass $class,
        JurisdictionalControlCode $jurisdictionalCode,
        ?string $restrictions = null,
    ): self {
        return new self($class, $jurisdictionalCode, $restrictions);
    }

    public function class(): ControlledSubstanceClass
    {
        return $this->class;
    }

    public function jurisdictionalCode(): JurisdictionalControlCode
    {
        return $this->jurisdictionalCode;
    }

    public function restrictions(): ?string
    {
        return $this->restrictions;
    }

    public function equals(self $other): bool
    {
        return $this->class === $other->class
            && $this->jurisdictionalCode->equals($other->jurisdictionalCode)
            && $this->restrictions === $other->restrictions;
    }
}
