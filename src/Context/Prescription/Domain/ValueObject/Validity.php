<?php

declare(strict_types=1);

namespace App\Context\Prescription\Domain\ValueObject;

use App\Context\Prescription\Domain\Exception\InvalidValidityException;

/**
 * How long the ordonnance can be presented at a pharmacy.
 *
 * The span is kept alongside the dates because a renewal pushes the end date
 * out by the very same span it was issued with.
 */
final class Validity
{
    private function __construct(
        private readonly \DateTimeImmutable $issuedAt,
        private readonly \DateTimeImmutable $validUntil,
        private readonly int $spanDays,
    ) {
    }

    public static function of(\DateTimeImmutable $issuedAt, int $spanDays): self
    {
        if ($spanDays < 1) {
            throw new InvalidValidityException('A prescription is valid at least one day.');
        }

        return new self($issuedAt, $issuedAt->modify(\sprintf('+%d days', $spanDays)), $spanDays);
    }

    public static function reconstitute(
        \DateTimeImmutable $issuedAt,
        \DateTimeImmutable $validUntil,
        int $spanDays,
    ): self {
        if ($validUntil < $issuedAt) {
            throw new InvalidValidityException('A prescription cannot expire before it is issued.');
        }

        return new self($issuedAt, $validUntil, max(1, $spanDays));
    }

    public function issuedAt(): \DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function validUntil(): \DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function spanDays(): int
    {
        return $this->spanDays;
    }

    public function isExpiredAt(\DateTimeImmutable $now): bool
    {
        return $now > $this->validUntil;
    }

    /**
     * Pushes the end date out by one more span — what renewing a prescription
     * means for the client holding it.
     */
    public function extended(): self
    {
        return new self(
            $this->issuedAt,
            $this->validUntil->modify(\sprintf('+%d days', $this->spanDays)),
            $this->spanDays,
        );
    }
}
