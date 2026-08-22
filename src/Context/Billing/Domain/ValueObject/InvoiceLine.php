<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\ValueObject;

use App\Context\Billing\Domain\Exception\InvalidInvoiceLineException;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;

/**
 * One frozen line of an invoice.
 *
 * Net, tax and gross are stored, not derived: the VAT rate, the price list and
 * the catalog entry may all change tomorrow, and the invoice must keep showing
 * the figures it was issued with. The three amounts are checked against each
 * other here so an inconsistent line can never reach the database.
 */
final class InvoiceLine
{
    private function __construct(
        private readonly InvoiceLineId $id,
        private readonly string $label,
        private readonly ?string $code,
        private readonly string $quantity,
        private readonly Money $unitNetPrice,
        private readonly ?string $taxCategoryCode,
        private readonly string $taxRatePercent,
        private readonly Money $netAmount,
        private readonly Money $taxAmount,
        private readonly Money $grossAmount,
        private readonly InvoiceLineSource $source,
        private readonly ?string $sourceReference,
    ) {
    }

    public static function create(
        InvoiceLineId $id,
        string $label,
        ?string $code,
        string $quantity,
        Money $unitNetPrice,
        ?string $taxCategoryCode,
        string $taxRatePercent,
        Money $netAmount,
        Money $taxAmount,
        Money $grossAmount,
        InvoiceLineSource $source,
        ?string $sourceReference = null,
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

        $currency = $netAmount->currency();

        $sameCurrency = $currency->equals($taxAmount->currency())
            && $currency->equals($grossAmount->currency())
            && $currency->equals($unitNetPrice->currency());

        if (!$sameCurrency) {
            throw new InvalidInvoiceLineException('All amounts of an invoice line must share one currency.');
        }

        if ($netAmount->minorUnits() + $taxAmount->minorUnits() !== $grossAmount->minorUnits()) {
            throw new InvalidInvoiceLineException(\sprintf(
                'Inconsistent invoice line: net (%d) + tax (%d) != gross (%d).',
                $netAmount->minorUnits(),
                $taxAmount->minorUnits(),
                $grossAmount->minorUnits(),
            ));
        }

        if (1 !== preg_match('/^\d+(\.\d{1,4})?$/', $taxRatePercent)) {
            throw new InvalidInvoiceLineException(
                \sprintf('Invalid tax rate "%s".', $taxRatePercent),
            );
        }

        return new self(
            $id,
            $label,
            null !== $code && '' !== mb_trim($code) ? mb_trim($code) : null,
            $quantity,
            $unitNetPrice,
            null !== $taxCategoryCode && '' !== mb_trim($taxCategoryCode) ? mb_trim($taxCategoryCode) : null,
            $taxRatePercent,
            $netAmount,
            $taxAmount,
            $grossAmount,
            $source,
            null !== $sourceReference && '' !== mb_trim($sourceReference) ? mb_trim($sourceReference) : null,
        );
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

    public function unitNetPrice(): Money
    {
        return $this->unitNetPrice;
    }

    public function taxCategoryCode(): ?string
    {
        return $this->taxCategoryCode;
    }

    public function taxRatePercent(): string
    {
        return $this->taxRatePercent;
    }

    public function netAmount(): Money
    {
        return $this->netAmount;
    }

    public function taxAmount(): Money
    {
        return $this->taxAmount;
    }

    public function grossAmount(): Money
    {
        return $this->grossAmount;
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
        return $this->netAmount->currency();
    }
}
