<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain;

use App\Context\Billing\Domain\Exception\InvalidCashClosingException;
use App\Context\Billing\Domain\ValueObject\CashClosingId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\ValueObject\CurrencyCode;

/**
 * « Clôture de caisse »: the end-of-day count of what is physically in the
 * till, against what the day's payments say should be there.
 *
 * Card and transfer takings are collected by the bank; cash and cheques are
 * counted by hand. A difference between the two is allowed — tills are not
 * perfect — but it has to be explained. A clinic closes each business day
 * once, and closings are numbered in sequence (the « ticket Z »).
 */
final class CashClosing extends AggregateRoot
{
    private const int MAX_COMMENT_LENGTH = 255;

    /**
     * @param array<string, int> $expectedMinorUnits payment method => expected amount
     */
    private function __construct(
        private readonly CashClosingId $id,
        private readonly ClinicId $clinicId,
        private readonly int $number,
        private readonly string $businessDate,
        private readonly array $expectedMinorUnits,
        private readonly ?int $countedCashMinorUnits,
        private readonly ?int $countedChequesMinorUnits,
        private readonly ?string $comment,
        private readonly CurrencyCode $currency,
        private readonly \DateTimeImmutable $closedAt,
        private readonly ?string $closedByUserId,
    ) {
    }

    /**
     * @param array<string, int> $expectedMinorUnits payment method => expected amount
     */
    public static function close(
        CashClosingId $id,
        ClinicId $clinicId,
        int $number,
        string $businessDate,
        array $expectedMinorUnits,
        ?int $countedCashMinorUnits,
        ?int $countedChequesMinorUnits,
        ?string $comment,
        CurrencyCode $currency,
        \DateTimeImmutable $now,
        ?string $closedByUserId = null,
    ): self {
        if ($number < 1) {
            throw new InvalidCashClosingException('A cash closing number starts at 1.');
        }

        if (1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/', $businessDate)) {
            throw new InvalidCashClosingException(\sprintf('Invalid business date "%s".', $businessDate));
        }

        foreach (array_keys($expectedMinorUnits) as $method) {
            if (null === PaymentMethod::tryFrom($method)) {
                throw new InvalidCashClosingException(\sprintf('Unknown payment method "%s".', $method));
            }
        }

        if (($countedCashMinorUnits ?? 0) < 0 || ($countedChequesMinorUnits ?? 0) < 0) {
            throw new InvalidCashClosingException('A counted amount cannot be negative.');
        }

        $comment = null !== $comment ? mb_trim($comment) : null;
        $comment = '' === $comment || null === $comment ? null : mb_substr($comment, 0, self::MAX_COMMENT_LENGTH);

        $closing = new self(
            $id,
            $clinicId,
            $number,
            $businessDate,
            $expectedMinorUnits,
            $countedCashMinorUnits,
            $countedChequesMinorUnits,
            $comment,
            $currency,
            $now,
            $closedByUserId,
        );

        if (0 !== $closing->gapMinorUnits() && null === $comment) {
            throw new InvalidCashClosingException('A difference between the till and the takings has to be explained.');
        }

        return $closing;
    }

    /**
     * @param array<string, int> $expectedMinorUnits
     */
    public static function reconstitute(
        CashClosingId $id,
        ClinicId $clinicId,
        int $number,
        string $businessDate,
        array $expectedMinorUnits,
        ?int $countedCashMinorUnits,
        ?int $countedChequesMinorUnits,
        ?string $comment,
        CurrencyCode $currency,
        \DateTimeImmutable $closedAt,
        ?string $closedByUserId,
    ): self {
        return new self(
            $id,
            $clinicId,
            $number,
            $businessDate,
            $expectedMinorUnits,
            $countedCashMinorUnits,
            $countedChequesMinorUnits,
            $comment,
            $currency,
            $closedAt,
            $closedByUserId,
        );
    }

    public function expectedFor(PaymentMethod $method): int
    {
        return $this->expectedMinorUnits[$method->value] ?? 0;
    }

    /**
     * Counted minus expected, over what is counted by hand (cash and cheques).
     * A drawer left uncounted is taken as matching.
     */
    public function gapMinorUnits(): int
    {
        $gap = 0;

        if (null !== $this->countedCashMinorUnits) {
            $gap += $this->countedCashMinorUnits - $this->expectedFor(PaymentMethod::CASH);
        }

        if (null !== $this->countedChequesMinorUnits) {
            $gap += $this->countedChequesMinorUnits - $this->expectedFor(PaymentMethod::CHECK);
        }

        return $gap;
    }

    public function totalExpectedMinorUnits(): int
    {
        return array_sum($this->expectedMinorUnits);
    }

    public function id(): CashClosingId
    {
        return $this->id;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function number(): int
    {
        return $this->number;
    }

    public function businessDate(): string
    {
        return $this->businessDate;
    }

    /**
     * @return array<string, int>
     */
    public function expectedMinorUnits(): array
    {
        return $this->expectedMinorUnits;
    }

    public function countedCashMinorUnits(): ?int
    {
        return $this->countedCashMinorUnits;
    }

    public function countedChequesMinorUnits(): ?int
    {
        return $this->countedChequesMinorUnits;
    }

    public function comment(): ?string
    {
        return $this->comment;
    }

    public function currency(): CurrencyCode
    {
        return $this->currency;
    }

    public function closedAt(): \DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function closedByUserId(): ?string
    {
        return $this->closedByUserId;
    }
}
