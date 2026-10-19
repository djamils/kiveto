<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\ValueObject;

use App\Context\Billing\Domain\Exception\InvalidInvoiceLineException;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\Shared\Money\Domain\ValueObject\UnitPrice;

/**
 * One line of an invoice or a credit note.
 *
 * The line keeps what was entered — the unit price as typed, whether it
 * included VAT, the quantity, the tax category with the rate and the nature
 * it resolved to — and carries the amounts the document calculated from it:
 * net (BT-131), gross for display, unit net at six decimals (BT-146). The
 * amounts are set by the document, never by whoever builds the line, and
 * freeze with it at issue: the rate, the price list and the catalogue entry
 * may all change tomorrow, an issued invoice keeps its figures.
 */
final class InvoiceLine
{
    private function __construct(
        private readonly InvoiceLineId $id,
        private readonly string $label,
        private readonly ?string $code,
        private readonly string $quantity,
        private readonly Money $enteredUnitPrice,
        private readonly PriceMode $priceMode,
        private readonly ?string $taxCategoryCode,
        private readonly string $taxRatePercent,
        private readonly ?OperationCategory $operationCategory,
        private readonly InvoiceLineSource $source,
        private readonly ?string $sourceReference,
        private readonly LineAmounts $amounts,
    ) {
    }

    /**
     * A line whose amounts are not calculated yet: the document it is added
     * to calculates them.
     */
    public static function create(
        InvoiceLineId $id,
        string $label,
        ?string $code,
        string $quantity,
        Money $enteredUnitPrice,
        PriceMode $priceMode,
        ?string $taxCategoryCode,
        string $taxRatePercent,
        ?OperationCategory $operationCategory,
        InvoiceLineSource $source,
        ?string $sourceReference = null,
    ): self {
        $currency = $enteredUnitPrice->currency();

        return self::reconstitute(
            $id,
            $label,
            $code,
            $quantity,
            $enteredUnitPrice,
            $priceMode,
            $taxCategoryCode,
            $taxRatePercent,
            $operationCategory,
            $source,
            $sourceReference,
            LineAmounts::of(Money::zero($currency), Money::zero($currency), UnitPrice::fromMicroUnits(0, $currency)),
        );
    }

    /**
     * A line with the amounts its document calculated (and stored, once issued).
     */
    public static function reconstitute(
        InvoiceLineId $id,
        string $label,
        ?string $code,
        string $quantity,
        Money $enteredUnitPrice,
        PriceMode $priceMode,
        ?string $taxCategoryCode,
        string $taxRatePercent,
        ?OperationCategory $operationCategory,
        InvoiceLineSource $source,
        ?string $sourceReference,
        LineAmounts $amounts,
    ): self {
        $label = mb_trim($label);

        if ('' === $label) {
            throw new InvalidInvoiceLineException('An invoice line needs a label.');
        }

        if (1 !== preg_match('/^-?\d+(\.\d{1,2})?$/', $quantity)) {
            throw new InvalidInvoiceLineException(
                \sprintf('Invalid quantity "%s": expected a decimal with at most 2 places.', $quantity),
            );
        }

        /** @var numeric-string $quantity */
        if (0 === bccomp($quantity, '0', 2)) {
            throw new InvalidInvoiceLineException('An invoice line cannot have a zero quantity.');
        }

        if (1 !== preg_match('/^\d+(\.\d{1,4})?$/', $taxRatePercent)) {
            throw new InvalidInvoiceLineException(
                \sprintf('Invalid tax rate "%s".', $taxRatePercent),
            );
        }

        self::assertCurrency($enteredUnitPrice->currency(), $amounts);

        return new self(
            $id,
            $label,
            null !== $code && '' !== mb_trim($code) ? mb_trim($code) : null,
            $quantity,
            $enteredUnitPrice,
            $priceMode,
            null !== $taxCategoryCode && '' !== mb_trim($taxCategoryCode) ? mb_trim($taxCategoryCode) : null,
            $taxRatePercent,
            $operationCategory,
            $source,
            null !== $sourceReference && '' !== mb_trim($sourceReference) ? mb_trim($sourceReference) : null,
            $amounts,
        );
    }

    /**
     * The same line with the amounts its document calculated.
     */
    public function withAmounts(LineAmounts $amounts): self
    {
        self::assertCurrency($this->currency(), $amounts);

        return new self(
            $this->id,
            $this->label,
            $this->code,
            $this->quantity,
            $this->enteredUnitPrice,
            $this->priceMode,
            $this->taxCategoryCode,
            $this->taxRatePercent,
            $this->operationCategory,
            $this->source,
            $this->sourceReference,
            $amounts,
        );
    }

    /**
     * What the amount calculator needs from this line.
     */
    public function calculationLine(): CalculationLine
    {
        return new CalculationLine($this->enteredUnitPrice, $this->priceMode, $this->quantity, $this->taxRatePercent);
    }

    public function id(): InvoiceLineId
    {
        return $this->id;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function code(): ?string
    {
        return $this->code;
    }

    public function quantity(): string
    {
        return $this->quantity;
    }

    /**
     * The unit price as entered, VAT included or not according to priceMode().
     */
    public function enteredUnitPrice(): Money
    {
        return $this->enteredUnitPrice;
    }

    public function priceMode(): PriceMode
    {
        return $this->priceMode;
    }

    public function taxCategoryCode(): ?string
    {
        return $this->taxCategoryCode;
    }

    public function taxRatePercent(): string
    {
        return $this->taxRatePercent;
    }

    /**
     * Goods or services; null when the tax category could not be resolved.
     */
    public function operationCategory(): ?OperationCategory
    {
        return $this->operationCategory;
    }

    public function amounts(): LineAmounts
    {
        return $this->amounts;
    }

    /** The line net amount (BT-131), before any document-level discount. */
    public function netAmount(): Money
    {
        return $this->amounts->net();
    }

    /** The line amount incl. VAT, before any document-level discount — for display. */
    public function grossAmount(): Money
    {
        return $this->amounts->gross();
    }

    /** The unit net price at six decimals (BT-146). */
    public function unitNet(): UnitPrice
    {
        return $this->amounts->unitNet();
    }

    public function source(): InvoiceLineSource
    {
        return $this->source;
    }

    public function sourceReference(): ?string
    {
        return $this->sourceReference;
    }

    public function currency(): CurrencyCode
    {
        return $this->enteredUnitPrice->currency();
    }

    private static function assertCurrency(CurrencyCode $currency, LineAmounts $amounts): void
    {
        $sameCurrency = $currency->equals($amounts->net()->currency())
            && $currency->equals($amounts->gross()->currency())
            && $currency->equals($amounts->unitNet()->currency());

        if (!$sameCurrency) {
            throw new InvalidInvoiceLineException('All amounts of an invoice line must share one currency.');
        }
    }
}
