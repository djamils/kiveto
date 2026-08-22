<?php

declare(strict_types=1);

namespace App\Context\Prescription\Domain\ValueObject;

/**
 * One time the ordonnance was presented again, kept as a trace.
 */
final class Renewal
{
    private function __construct(
        private readonly RenewalId $id,
        private readonly \DateTimeImmutable $renewedAt,
        private readonly \DateTimeImmutable $newValidUntil,
        private readonly ?string $renewedByUserId,
    ) {
    }

    public static function create(
        RenewalId $id,
        \DateTimeImmutable $renewedAt,
        \DateTimeImmutable $newValidUntil,
        ?string $renewedByUserId = null,
    ): self {
        return new self($id, $renewedAt, $newValidUntil, $renewedByUserId);
    }

    public function id(): RenewalId
    {
        return $this->id;
    }

    public function renewedAt(): \DateTimeImmutable
    {
        return $this->renewedAt;
    }

    public function newValidUntil(): \DateTimeImmutable
    {
        return $this->newValidUntil;
    }

    public function renewedByUserId(): ?string
    {
        return $this->renewedByUserId;
    }
}
