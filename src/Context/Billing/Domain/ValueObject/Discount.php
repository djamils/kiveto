<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\ValueObject;

use App\Context\Billing\Domain\Exception\InvalidDiscountException;
use App\Shared\Money\Domain\ValueObject\Money;

/**
 * A commercial gesture applied to the whole invoice, before tax.
 *
 * Kept as the reason plus the raw parameter rather than a computed amount, so
 * the invoice can print "-10 % (client fidèle)" and not just a bare figure.
 */
final class Discount
{
    private function __construct(
        private readonly DiscountKind $kind,
        private readonly string $value,
        private readonly ?string $reason,
    ) {
    }

    public static function percentage(string $percent, ?string $reason = null): self
    {
        if (1 !== preg_match('/^\d+(\.\d{1,2})?$/', $percent)) {
            throw new InvalidDiscountException(\sprintf('Invalid discount percentage "%s".', $percent));
        }

        /** @var numeric-string $percent */
        if (bccomp($percent, '100', 2) > 0 || 0 === bccomp($percent, '0', 2)) {
            throw new InvalidDiscountException('A percentage discount must be above 0 and at most 100.');
        }

        return new self(DiscountKind::PERCENTAGE, $percent, self::cleanReason($reason));
    }

    public static function fixed(Money $amount, ?string $reason = null): self
    {
        if (!$amount->isPositive()) {
            throw new InvalidDiscountException('A fixed discount must be a positive amount.');
        }

        return new self(DiscountKind::FIXED, (string) $amount->minorUnits(), self::cleanReason($reason));
    }

    public static function reconstitute(DiscountKind $kind, string $value, ?string $reason): self
    {
        return DiscountKind::PERCENTAGE === $kind
            ? self::percentage($value, $reason)
            : new self(DiscountKind::FIXED, $value, self::cleanReason($reason));
    }

    public function kind(): DiscountKind
    {
        return $this->kind;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    /**
     * The amount taken off a net total, never more than the total itself.
     */
    public function amountOff(int $netMinorUnits): int
    {
        if ($netMinorUnits <= 0) {
            return 0;
        }

        $off = DiscountKind::PERCENTAGE === $this->kind
            ? (int) round($netMinorUnits * (float) $this->value / 100)
            : (int) $this->value;

        return min($off, $netMinorUnits);
    }

    private static function cleanReason(?string $reason): ?string
    {
        if (null === $reason) {
            return null;
        }

        $reason = mb_trim($reason);

        return '' === $reason ? null : $reason;
    }
}
