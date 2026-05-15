<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\ValueObject;

use App\System\Money\Domain\ValueObject\Money;
use App\System\Money\Domain\ValueObject\RoundingPolicyId;

final class TaxApplication
{
    private function __construct(
        private readonly TaxRegimeId $taxRegimeId,
        private readonly TaxCategoryCode $taxCategoryCode,
        private readonly AppliedTaxRate $appliedRate,
        private readonly Money $netAmount,
        private readonly Money $taxAmount,
        private readonly Money $grossAmount,
        private readonly RoundingPolicyId $roundingPolicyId,
        private readonly LegalMentions $legalMentions,
        private readonly FiscalContext $fiscalContext,
        private readonly \DateTimeImmutable $resolvedAt,
    ) {
    }

    public static function of(
        TaxRegimeId $taxRegimeId,
        TaxCategoryCode $taxCategoryCode,
        AppliedTaxRate $appliedRate,
        Money $netAmount,
        Money $taxAmount,
        Money $grossAmount,
        RoundingPolicyId $roundingPolicyId,
        LegalMentions $legalMentions,
        FiscalContext $fiscalContext,
        \DateTimeImmutable $resolvedAt,
    ): self {
        if (!$netAmount->currency()->equals($taxAmount->currency()) || !$taxAmount->currency()->equals($grossAmount->currency())) {
            throw new \LogicException('Currency mismatch in TaxApplication: netAmount, taxAmount, grossAmount must share the same currency.');
        }

        if ($netAmount->minorUnits() + $taxAmount->minorUnits() !== $grossAmount->minorUnits()) {
            throw new \LogicException(\sprintf(
                'TaxApplication invariant violated: net (%d) + tax (%d) != gross (%d).',
                $netAmount->minorUnits(),
                $taxAmount->minorUnits(),
                $grossAmount->minorUnits(),
            ));
        }

        return new self(
            $taxRegimeId,
            $taxCategoryCode,
            $appliedRate,
            $netAmount,
            $taxAmount,
            $grossAmount,
            $roundingPolicyId,
            $legalMentions,
            $fiscalContext,
            $resolvedAt,
        );
    }

    public function taxRegimeId(): TaxRegimeId
    {
        return $this->taxRegimeId;
    }

    public function taxCategoryCode(): TaxCategoryCode
    {
        return $this->taxCategoryCode;
    }

    public function appliedRate(): AppliedTaxRate
    {
        return $this->appliedRate;
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

    public function roundingPolicyId(): RoundingPolicyId
    {
        return $this->roundingPolicyId;
    }

    public function legalMentions(): LegalMentions
    {
        return $this->legalMentions;
    }

    public function fiscalContext(): FiscalContext
    {
        return $this->fiscalContext;
    }

    public function resolvedAt(): \DateTimeImmutable
    {
        return $this->resolvedAt;
    }
}
