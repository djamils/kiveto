<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Adapter\ElectronicInvoicing;

use App\Context\Billing\Domain\CreditNote;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\ValueObject\BuyerFiscalIdentity;
use App\Context\Billing\Domain\ValueObject\CalculatedAmounts;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\PaymentTerms;
use App\Context\Billing\Domain\ValueObject\RateBucket;
use App\Context\Billing\Domain\ValueObject\SellerIdentity;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableAddress;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableAllowance;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableElectronicAddress;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableInvoice;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableLine;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableNote;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableParty;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\VatBreakdown;

/**
 * Translates an issued invoice or credit note into the neutral document the
 * electronic-invoicing context transmits — from its stored, frozen amounts,
 * never recalculated.
 *
 * Each line is named exactly as it prints on the PDF (BT-153: the catalogue or
 * consultation label). Nothing else of the visit leaves the clinic: not the
 * animal, not the invoice notes, not any free text of the consultation.
 */
final readonly class TransmittableInvoiceMapper
{
    private const string VAT_CATEGORY = 'S';
    private const string UNIT_CODE    = 'C62';
    private const string ALLOWANCE    = 'Remise';

    public function fromInvoice(Invoice $invoice, SellerIdentity $seller): TransmittableInvoice
    {
        $issuedAt = $invoice->issuedAt() ?? throw new \LogicException('Only an issued invoice is transmitted.');
        $amounts  = $invoice->amounts();
        $terms    = $invoice->paymentTerms();
        $paid     = $invoice->amountPaid()->minorUnits();

        return new TransmittableInvoice(
            documentId: $invoice->id()->toString(),
            kind: TransmittableInvoice::KIND_INVOICE,
            number: (string) $invoice->number()?->toString(),
            issueDate: $issuedAt,
            typeCode: $invoice->typeCode()->value,
            invoicingFramework: (string) $invoice->framework()?->code(),
            processingRule: $invoice->processingRule()->value,
            currency: $invoice->currency()->toString(),
            seller: $this->seller($seller),
            buyer: $this->buyer($invoice->payer()),
            buyerContact: $invoice->payer()->attention(),
            // « Date d'exécution des prestations » on the PDF: the issue date.
            deliveryDate: $issuedAt,
            dueDate: $terms?->dueDate,
            paymentTermsNote: $terms?->note,
            lineTotal: $amounts->totalLineNet()->minorUnits(),
            allowanceTotal: $amounts->totalAllowance()->minorUnits(),
            taxBasis: $amounts->taxBasis()->minorUnits(),
            vatTotal: $amounts->totalVat()->minorUnits(),
            grossTotal: $amounts->totalGross()->minorUnits(),
            paidAmount: $paid,
            duePayable: $amounts->totalGross()->minorUnits() - $paid,
            lines: $this->lines($invoice->lines()),
            allowances: $this->allowances($amounts),
            vatBreakdown: $this->breakdown($amounts),
            notes: $this->notes($seller),
        );
    }

    public function fromCreditNote(CreditNote $creditNote, SellerIdentity $seller): TransmittableInvoice
    {
        $amounts = $creditNote->amounts();

        return new TransmittableInvoice(
            documentId: $creditNote->id()->toString(),
            kind: TransmittableInvoice::KIND_CREDIT_NOTE,
            number: $creditNote->number()->toString(),
            issueDate: $creditNote->issuedAt(),
            typeCode: $creditNote->typeCode()->value,
            invoicingFramework: (string) $creditNote->framework()?->code(),
            processingRule: $creditNote->processingRule()->value,
            currency: $creditNote->currency()->toString(),
            seller: $this->seller($seller),
            buyer: $this->buyer($creditNote->payer()),
            buyerContact: $creditNote->payer()->attention(),
            deliveryDate: $creditNote->issuedAt(),
            dueDate: null,
            paymentTermsNote: null,
            lineTotal: $amounts->totalLineNet()->minorUnits(),
            allowanceTotal: $amounts->totalAllowance()->minorUnits(),
            taxBasis: $amounts->taxBasis()->minorUnits(),
            vatTotal: $amounts->totalVat()->minorUnits(),
            grossTotal: $amounts->totalGross()->minorUnits(),
            paidAmount: 0,
            duePayable: $amounts->totalGross()->minorUnits(),
            lines: $this->lines($creditNote->lines()),
            allowances: $this->allowances($amounts),
            vatBreakdown: $this->breakdown($amounts),
            notes: $this->notes($seller),
            precedingInvoiceNumber: $creditNote->invoiceNumber(),
            precedingInvoiceDate: $creditNote->invoiceIssuedAt(),
        );
    }

    private function seller(SellerIdentity $seller): TransmittableParty
    {
        $siren = (string) $seller->siren?->value();

        return new TransmittableParty(
            name: $seller->legalName,
            siren: $siren,
            vatNumber: $seller->vatNumber?->value(),
            address: new TransmittableAddress($seller->streetLines, $seller->postalCode, $seller->city, $seller->countryCode),
            electronicAddress: new TransmittableElectronicAddress(BuyerFiscalIdentity::DEFAULT_ELECTRONIC_ADDRESS_SCHEME, $siren),
        );
    }

    private function buyer(Payer $payer): TransmittableParty
    {
        $identity = $payer->fiscalIdentity() ?? throw new \LogicException('Only a professional document is transmitted.');

        return new TransmittableParty(
            name: $identity->legalName() ?? $payer->displayName(),
            siren: (string) $identity->siren()?->value(),
            vatNumber: $identity->vatNumber()?->value(),
            address: new TransmittableAddress($identity->streetLines(), $identity->postalCode(), $identity->city(), $identity->countryCode()),
            electronicAddress: new TransmittableElectronicAddress(
                (string) $identity->electronicAddressScheme(),
                (string) $identity->electronicAddress(),
            ),
        );
    }

    /**
     * @param list<InvoiceLine> $lines
     *
     * @return list<TransmittableLine>
     */
    private function lines(array $lines): array
    {
        return array_map(
            static fn (InvoiceLine $line, int $index): TransmittableLine => new TransmittableLine(
                lineId: (string) ($index + 1),
                name: $line->label(),
                quantity: $line->quantity(),
                unitCode: self::UNIT_CODE,
                unitNet: $line->unitNet()->toDecimalString(),
                net: $line->netAmount()->minorUnits(),
                vatCategory: self::VAT_CATEGORY,
                vatRate: $line->taxRatePercent(),
            ),
            $lines,
            array_keys($lines),
        );
    }

    /**
     * @return list<TransmittableAllowance>
     */
    private function allowances(CalculatedAmounts $amounts): array
    {
        $allowances = [];

        foreach ($amounts->buckets() as $bucket) {
            if ($bucket->allowance()->isPositive()) {
                $allowances[] = new TransmittableAllowance($bucket->allowance()->minorUnits(), self::VAT_CATEGORY, $bucket->ratePercent(), self::ALLOWANCE);
            }
        }

        return $allowances;
    }

    /**
     * @return list<VatBreakdown>
     */
    private function breakdown(CalculatedAmounts $amounts): array
    {
        return array_map(
            static fn (RateBucket $bucket): VatBreakdown => new VatBreakdown(self::VAT_CATEGORY, $bucket->ratePercent(), $bucket->base()->minorUnits(), $bucket->vat()->minorUnits()),
            $amounts->buckets(),
        );
    }

    /**
     * The professional mentions (PMD, PMT, AAB) and the seller's legal
     * sentence (REG).
     *
     * @return list<TransmittableNote>
     */
    private function notes(SellerIdentity $seller): array
    {
        $notes = [];

        if (null !== $seller->legalSentence && '' !== mb_trim($seller->legalSentence)) {
            $notes[] = new TransmittableNote('REG', mb_trim($seller->legalSentence));
        }

        foreach (PaymentTerms::PROFESSIONAL_MENTIONS as $code => $text) {
            $notes[] = new TransmittableNote($code, $text);
        }

        return $notes;
    }
}
