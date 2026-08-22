<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\ValueObject;

use App\Context\Billing\Domain\Exception\InvalidPaymentException;
use App\Shared\Money\Domain\ValueObject\Money;

/**
 * Money actually received against an invoice.
 *
 * Payments are append-only: a mistake is corrected by a new, opposite movement
 * in the accounting sense, never by editing what was recorded.
 */
final class Payment
{
    private function __construct(
        private readonly PaymentId $id,
        private readonly PaymentMethod $method,
        private readonly Money $amount,
        private readonly \DateTimeImmutable $receivedAt,
        private readonly ?string $reference,
        private readonly ?string $recordedByUserId,
    ) {
    }

    public static function create(
        PaymentId $id,
        PaymentMethod $method,
        Money $amount,
        \DateTimeImmutable $receivedAt,
        ?string $reference = null,
        ?string $recordedByUserId = null,
    ): self {
        if (!$amount->isPositive()) {
            throw new InvalidPaymentException('A payment must be a positive amount.');
        }

        return new self(
            $id,
            $method,
            $amount,
            $receivedAt,
            null !== $reference && '' !== mb_trim($reference) ? mb_trim($reference) : null,
            $recordedByUserId,
        );
    }

    public function id(): PaymentId
    {
        return $this->id;
    }

    public function method(): PaymentMethod
    {
        return $this->method;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function receivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function reference(): ?string
    {
        return $this->reference;
    }

    public function recordedByUserId(): ?string
    {
        return $this->recordedByUserId;
    }
}
