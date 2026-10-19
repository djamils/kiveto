<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Policy;

use App\Context\Billing\Domain\Exception\InvoiceNotTransmissibleException;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\ValueObject\ProcessingRule;
use App\Context\Billing\Domain\ValueObject\ReadinessRequirement;
use App\Context\Billing\Domain\ValueObject\SellerIdentity;

/**
 * Whether a professional draft holds everything an electronic invoice needs,
 * checked before a number is drawn — locally, never over the network (the
 * platform directory is looked up later, when the invoice is sent).
 *
 * A private client's invoice has no such requirement. Payment terms, legal
 * mentions and the invoicing framework are always computable once the lines
 * pass, so they are not checked.
 */
final class ElectronicInvoiceReadiness
{
    /**
     * @return list<ReadinessRequirement> what is missing, empty when the draft can be issued
     */
    public function check(Invoice $draft, SellerIdentity $seller): array
    {
        if (ProcessingRule::B2B !== $draft->processingRule()) {
            return [];
        }

        $missing = [];
        $buyer   = $draft->payer()->fiscalIdentity();

        if (null === $buyer?->legalName()) {
            $missing[] = ReadinessRequirement::BUYER_LEGAL_NAME;
        }

        if (null === $buyer?->siren()) {
            $missing[] = ReadinessRequirement::BUYER_SIREN;
        }

        if (true !== $buyer?->hasCompleteAddress()) {
            $missing[] = ReadinessRequirement::BUYER_ADDRESS;
        }

        $uncategorised = false;
        $zeroRate      = false;

        foreach ($draft->lines() as $line) {
            $uncategorised = $uncategorised || null === $line->operationCategory();
            /** @var numeric-string $rate */
            $rate     = $line->taxRatePercent();
            $zeroRate = $zeroRate || (null !== $line->operationCategory() && 0 === bccomp($rate, '0', 4));
        }

        if ($uncategorised) {
            $missing[] = ReadinessRequirement::LINE_TAX_CATEGORY;
        }

        // Exemption reasons (BT-120) are not modelled yet.
        if ($zeroRate) {
            $missing[] = ReadinessRequirement::LINE_ZERO_RATE;
        }

        if (null === $seller->siren) {
            $missing[] = ReadinessRequirement::SELLER_SIREN;
        }

        if (null === $seller->vatNumber) {
            $missing[] = ReadinessRequirement::SELLER_VAT;
        }

        if (!$seller->hasCompleteAddress()) {
            $missing[] = ReadinessRequirement::SELLER_ADDRESS;
        }

        if ('' === mb_trim($seller->legalName)) {
            $missing[] = ReadinessRequirement::SELLER_LEGAL_NAME;
        }

        if (!$seller->numberingIsUnique) {
            $missing[] = ReadinessRequirement::SELLER_NUMBERING_NOT_UNIQUE;
        }

        return $missing;
    }

    /**
     * @throws InvoiceNotTransmissibleException
     */
    public function assertReady(Invoice $draft, SellerIdentity $seller): void
    {
        $missing = $this->check($draft, $seller);

        if ([] !== $missing) {
            throw new InvoiceNotTransmissibleException($draft->id()->toString(), $missing);
        }
    }
}
