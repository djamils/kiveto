<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\TaxProfile;

/**
 * The registry's answer for the client's SIREN, with the official name when it gave one.
 */
final class CompanyCheck
{
    private CompanyCheckOutcome $outcome;
    private ?string $registryName;
    private ?\DateTimeImmutable $checkedAt;

    private function __construct(CompanyCheckOutcome $outcome, ?string $registryName, ?\DateTimeImmutable $checkedAt)
    {
        $this->outcome      = $outcome;
        $this->registryName = $registryName;
        $this->checkedAt    = $checkedAt;
    }

    public static function unchecked(): self
    {
        return new self(CompanyCheckOutcome::UNCHECKED, null, null);
    }

    public static function checked(CompanyCheckOutcome $outcome, ?string $registryName, \DateTimeImmutable $checkedAt): self
    {
        if (CompanyCheckOutcome::UNCHECKED === $outcome) {
            return self::unchecked();
        }

        $name = trim((string) $registryName);

        return new self($outcome, '' === $name ? null : $name, $checkedAt);
    }

    public function outcome(): CompanyCheckOutcome
    {
        return $this->outcome;
    }

    public function registryName(): ?string
    {
        return $this->registryName;
    }

    public function checkedAt(): ?\DateTimeImmutable
    {
        return $this->checkedAt;
    }

    public function equals(self $other): bool
    {
        return $this->outcome === $other->outcome
            && $this->registryName === $other->registryName
            && $this->checkedAt?->getTimestamp() === $other->checkedAt?->getTimestamp();
    }
}
