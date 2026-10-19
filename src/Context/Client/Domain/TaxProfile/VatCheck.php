<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\TaxProfile;

/**
 * VIES's answer for the client's VAT number.
 */
final class VatCheck
{
    private VatCheckOutcome $outcome;
    private ?\DateTimeImmutable $checkedAt;

    private function __construct(VatCheckOutcome $outcome, ?\DateTimeImmutable $checkedAt)
    {
        $this->outcome   = $outcome;
        $this->checkedAt = $checkedAt;
    }

    public static function unchecked(): self
    {
        return new self(VatCheckOutcome::UNCHECKED, null);
    }

    public static function checked(VatCheckOutcome $outcome, \DateTimeImmutable $checkedAt): self
    {
        if (VatCheckOutcome::UNCHECKED === $outcome) {
            return self::unchecked();
        }

        return new self($outcome, $checkedAt);
    }

    public function outcome(): VatCheckOutcome
    {
        return $this->outcome;
    }

    public function checkedAt(): ?\DateTimeImmutable
    {
        return $this->checkedAt;
    }

    public function equals(self $other): bool
    {
        return $this->outcome === $other->outcome
            && $this->checkedAt?->getTimestamp() === $other->checkedAt?->getTimestamp();
    }
}
