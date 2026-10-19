<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Service;

use App\Context\Billing\Application\Port\TaxResolverInterface;
use App\Context\Billing\Domain\Exception\InvalidInvoiceLineException;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Context\Billing\Domain\ValueObject\PriceMode;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Money\Domain\ValueObject\Money;

/**
 * Builds an invoice line from a price as entered, freezing the VAT rate and
 * the nature its tax category resolves to for the clinic.
 *
 * A category that cannot be resolved leaves the line at 0 % with no nature:
 * the counter can still take a private client's money, and a professional
 * invoice refuses such a line before it is numbered. The amounts are left to
 * the invoice the line is added to.
 */
final readonly class InvoiceLineFactory
{
    public function __construct(
        private TaxResolverInterface $taxResolver,
        private UuidGeneratorInterface $uuidGenerator,
    ) {
    }

    public function line(
        string $clinicId,
        string $label,
        ?string $code,
        string $quantity,
        Money $enteredUnitPrice,
        PriceMode $priceMode,
        ?string $taxCategoryCode,
        InvoiceLineSource $source,
        ?string $sourceReference = null,
        bool $categoryRequired = false,
    ): InvoiceLine {
        $tax = $this->taxResolver->resolve($clinicId, $taxCategoryCode, $enteredUnitPrice->currency()->toString());

        if (null === $tax && $categoryRequired) {
            throw new InvalidInvoiceLineException(\sprintf('Unknown tax category "%s".', $taxCategoryCode ?? ''));
        }

        return InvoiceLine::create(
            id: InvoiceLineId::fromString($this->uuidGenerator->generate()),
            label: $label,
            code: $code,
            quantity: $quantity,
            enteredUnitPrice: $enteredUnitPrice,
            priceMode: $priceMode,
            taxCategoryCode: $taxCategoryCode,
            taxRatePercent: $tax->ratePercent ?? '0',
            operationCategory: $tax?->nature,
            source: $source,
            sourceReference: $sourceReference,
        );
    }
}
