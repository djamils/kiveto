<?php

declare(strict_types=1);

namespace App\Context\Consultation\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

/**
 * One line of the consultation's billing draft.
 *
 * Billing lines are never created by hand: they are derived by the aggregate
 * from billable plan actions and prescription lines, matched by `sourceLineId`
 * so that re-deriving keeps both the line id and its price snapshot.
 */
final readonly class BillingLineRecord
{
    private function __construct(
        private string $id,
        private string $sourceLineId,
        private BillingLineSource $source,
        private string $label,
        private ?string $code,
        private float $quantity,
        private int $unitPriceMinorUnits,
        private string $currency,
        private ?string $taxCategoryCode,
    ) {
    }

    public static function create(
        string $sourceLineId,
        BillingLineSource $source,
        string $label,
        ?string $code,
        float $quantity,
        int $unitPriceMinorUnits,
        string $currency,
        ?string $taxCategoryCode,
    ): self {
        $label = trim($label);

        if ('' === $label) {
            throw new \InvalidArgumentException('Billing line label cannot be empty');
        }

        if (round($quantity, 2) <= 0) {
            throw new \InvalidArgumentException('Billing line quantity must be positive');
        }

        if ($unitPriceMinorUnits < 0) {
            throw new \InvalidArgumentException('Billing line price cannot be negative');
        }

        if ('' === trim($currency)) {
            throw new \InvalidArgumentException('Billing line price requires a currency');
        }

        return new self(
            Uuid::v7()->toString(),
            $sourceLineId,
            $source,
            $label,
            $code,
            $quantity,
            $unitPriceMinorUnits,
            $currency,
            $taxCategoryCode,
        );
    }

    public static function reconstitute(
        string $id,
        string $sourceLineId,
        BillingLineSource $source,
        string $label,
        ?string $code,
        float $quantity,
        int $unitPriceMinorUnits,
        string $currency,
        ?string $taxCategoryCode,
    ): self {
        return new self(
            $id,
            $sourceLineId,
            $source,
            $label,
            $code,
            $quantity,
            $unitPriceMinorUnits,
            $currency,
            $taxCategoryCode,
        );
    }

    /**
     * Propagates label/quantity changes from the source line while preserving
     * this line's id and its snapshotted unit price.
     */
    public function withSourceDetails(string $label, ?string $code, float $quantity): self
    {
        $label = trim($label);

        if ('' === $label) {
            throw new \InvalidArgumentException('Billing line label cannot be empty');
        }

        if (round($quantity, 2) <= 0) {
            throw new \InvalidArgumentException('Billing line quantity must be positive');
        }

        return new self(
            $this->id,
            $this->sourceLineId,
            $this->source,
            $label,
            $code,
            $quantity,
            $this->unitPriceMinorUnits,
            $this->currency,
            $this->taxCategoryCode,
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSourceLineId(): string
    {
        return $this->sourceLineId;
    }

    public function getSource(): BillingLineSource
    {
        return $this->source;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function getUnitPriceMinorUnits(): int
    {
        return $this->unitPriceMinorUnits;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getTaxCategoryCode(): ?string
    {
        return $this->taxCategoryCode;
    }

    /**
     * Line total in minor units, rounded half-up to the nearest minor unit.
     */
    public function getTotalMinorUnits(): int
    {
        return (int) round($this->unitPriceMinorUnits * $this->quantity);
    }
}
