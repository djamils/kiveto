<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Format\Cii;

use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableAddress;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableAllowance;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableElectronicAddress;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableInvoice;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableLine;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableParty;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\VatBreakdown;
use App\System\ElectronicInvoicing\Application\Exception\RenderingFailedException;
use App\System\ElectronicInvoicing\Application\Port\InvoiceDocumentRendererInterface;
use App\System\ElectronicInvoicing\Infrastructure\Format\XsdValidator;

/**
 * Renders a transmittable invoice / credit note as a UN/CEFACT CII D22B
 * document following the EN16931 core (BT-24 urn:cen.eu:en16931:2017).
 *
 * Elements are written in the order of the XSD sequences, which is NOT the
 * EN16931 business-term order (e.g. a party's postal address comes before
 * its electronic address and its VAT registration; the VAT breakdown comes
 * before the document-level allowances, the payment terms and the totals).
 * Every rendered document is validated against the vendored XSD before it is
 * returned.
 */
final readonly class CiiInvoiceRenderer implements InvoiceDocumentRendererInterface
{
    private const string NS_RSM = 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100';
    private const string NS_RAM = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';
    private const string NS_QDT = 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100';
    private const string NS_UDT = 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100';

    private const string SPECIFICATION_ID = 'urn:cen.eu:en16931:2017';
    private const string SCHEME_SIREN     = '0002';
    private const string SCHEME_VAT       = 'VA';
    private const string TAX_TYPE_VAT     = 'VAT';
    private const string DATE_FORMAT      = '102';

    private const int UNIT_PRICE_SCALE = 6;
    private const int RATE_SCALE       = 2;

    public function __construct(
        private XsdValidator $validator,
        private string $schemaPath,
    ) {
    }

    public function render(TransmittableInvoice $invoice, TransmittableElectronicAddress $buyerElectronicAddress): string
    {
        $writer = new \XMLWriter();
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->setIndentString('  ');
        $writer->startDocument('1.0', 'UTF-8');

        $writer->startElement('rsm:CrossIndustryInvoice');
        $writer->writeAttribute('xmlns:rsm', self::NS_RSM);
        $writer->writeAttribute('xmlns:ram', self::NS_RAM);
        $writer->writeAttribute('xmlns:qdt', self::NS_QDT);
        $writer->writeAttribute('xmlns:udt', self::NS_UDT);

        $this->writeContext($writer, $invoice);
        $this->writeHeader($writer, $invoice);

        $writer->startElement('rsm:SupplyChainTradeTransaction');
        foreach ($invoice->lines as $line) {
            $this->writeLine($writer, $line);
        }
        $this->writeAgreement($writer, $invoice, $buyerElectronicAddress);
        $this->writeDelivery($writer, $invoice);
        $this->writeSettlement($writer, $invoice);
        $writer->endElement();

        $writer->endElement();
        $writer->endDocument();

        $xml = $writer->outputMemory();

        $errors = $this->validator->validate($xml, $this->schemaPath);
        if ([] !== $errors) {
            throw new RenderingFailedException($errors);
        }

        return $xml;
    }

    private function writeContext(\XMLWriter $writer, TransmittableInvoice $invoice): void
    {
        $writer->startElement('rsm:ExchangedDocumentContext');
        // BT-23
        $writer->startElement('ram:BusinessProcessSpecifiedDocumentContextParameter');
        $this->element($writer, 'ram:ID', $invoice->invoicingFramework);
        $writer->endElement();
        // BT-24
        $writer->startElement('ram:GuidelineSpecifiedDocumentContextParameter');
        $this->element($writer, 'ram:ID', self::SPECIFICATION_ID);
        $writer->endElement();
        $writer->endElement();
    }

    private function writeHeader(\XMLWriter $writer, TransmittableInvoice $invoice): void
    {
        $writer->startElement('rsm:ExchangedDocument');
        $this->element($writer, 'ram:ID', $invoice->number);
        $this->element($writer, 'ram:TypeCode', $invoice->typeCode);
        $this->dateTime($writer, 'ram:IssueDateTime', 'udt:DateTimeString', $invoice->issueDate);
        // BG-1 / BT-22 + BT-21
        foreach ($invoice->notes as $note) {
            $writer->startElement('ram:IncludedNote');
            $this->element($writer, 'ram:Content', $note->text);
            $this->element($writer, 'ram:SubjectCode', $note->subjectCode);
            $writer->endElement();
        }
        $writer->endElement();
    }

    /**
     * BG-25.
     */
    private function writeLine(\XMLWriter $writer, TransmittableLine $line): void
    {
        $writer->startElement('ram:IncludedSupplyChainTradeLineItem');

        $writer->startElement('ram:AssociatedDocumentLineDocument');
        $this->element($writer, 'ram:LineID', $line->lineId);
        $writer->endElement();

        $writer->startElement('ram:SpecifiedTradeProduct');
        $this->element($writer, 'ram:Name', $line->name);
        $writer->endElement();

        // BT-146; the gross price (BT-148) is optional and not rendered.
        $writer->startElement('ram:SpecifiedLineTradeAgreement');
        $writer->startElement('ram:NetPriceProductTradePrice');
        $this->element($writer, 'ram:ChargeAmount', self::decimal($line->unitNet, self::UNIT_PRICE_SCALE));
        $writer->endElement();
        $writer->endElement();

        $writer->startElement('ram:SpecifiedLineTradeDelivery');
        $this->element($writer, 'ram:BilledQuantity', $line->quantity, ['unitCode' => $line->unitCode]);
        $writer->endElement();

        $writer->startElement('ram:SpecifiedLineTradeSettlement');
        $this->categoryTax($writer, 'ram:ApplicableTradeTax', $line->vatCategory, $line->vatRate);
        $writer->startElement('ram:SpecifiedTradeSettlementLineMonetarySummation');
        $this->element($writer, 'ram:LineTotalAmount', self::amount($line->net));
        $writer->endElement();
        $writer->endElement();

        $writer->endElement();
    }

    private function writeAgreement(\XMLWriter $writer, TransmittableInvoice $invoice, TransmittableElectronicAddress $buyerElectronicAddress): void
    {
        $writer->startElement('ram:ApplicableHeaderTradeAgreement');
        $this->party($writer, 'ram:SellerTradeParty', $invoice->seller, null, $invoice->seller->electronicAddress);
        $this->party($writer, 'ram:BuyerTradeParty', $invoice->buyer, $invoice->buyerContact, $buyerElectronicAddress);
        $writer->endElement();
    }

    private function writeDelivery(\XMLWriter $writer, TransmittableInvoice $invoice): void
    {
        $writer->startElement('ram:ApplicableHeaderTradeDelivery');
        // BT-72
        if (null !== $invoice->deliveryDate) {
            $writer->startElement('ram:ActualDeliverySupplyChainEvent');
            $this->dateTime($writer, 'ram:OccurrenceDateTime', 'udt:DateTimeString', $invoice->deliveryDate);
            $writer->endElement();
        }
        $writer->endElement();
    }

    private function writeSettlement(\XMLWriter $writer, TransmittableInvoice $invoice): void
    {
        $writer->startElement('ram:ApplicableHeaderTradeSettlement');
        $this->element($writer, 'ram:InvoiceCurrencyCode', $invoice->currency);

        // BG-23, one per category + rate. DueDateTypeCode (BT-8) is omitted:
        // VAT on debits is not supported.
        foreach ($invoice->vatBreakdown as $breakdown) {
            $this->writeVatBreakdown($writer, $breakdown);
        }

        // BG-20
        foreach ($invoice->allowances as $allowance) {
            $this->writeAllowance($writer, $allowance);
        }

        // BT-20 / BT-9
        if (null !== $invoice->paymentTermsNote || null !== $invoice->dueDate) {
            $writer->startElement('ram:SpecifiedTradePaymentTerms');
            if (null !== $invoice->paymentTermsNote) {
                $this->element($writer, 'ram:Description', $invoice->paymentTermsNote);
            }
            if (null !== $invoice->dueDate) {
                $this->dateTime($writer, 'ram:DueDateDateTime', 'udt:DateTimeString', $invoice->dueDate);
            }
            $writer->endElement();
        }

        // BG-22
        $writer->startElement('ram:SpecifiedTradeSettlementHeaderMonetarySummation');
        $this->element($writer, 'ram:LineTotalAmount', self::amount($invoice->lineTotal));
        $this->element($writer, 'ram:AllowanceTotalAmount', self::amount($invoice->allowanceTotal));
        $this->element($writer, 'ram:TaxBasisTotalAmount', self::amount($invoice->taxBasis));
        $this->element($writer, 'ram:TaxTotalAmount', self::amount($invoice->vatTotal), ['currencyID' => $invoice->currency]);
        $this->element($writer, 'ram:GrandTotalAmount', self::amount($invoice->grossTotal));
        $this->element($writer, 'ram:TotalPrepaidAmount', self::amount($invoice->paidAmount));
        $this->element($writer, 'ram:DuePayableAmount', self::amount($invoice->duePayable));
        $writer->endElement();

        // BG-3: BT-25 / BT-26
        if (null !== $invoice->precedingInvoiceNumber) {
            $writer->startElement('ram:InvoiceReferencedDocument');
            $this->element($writer, 'ram:IssuerAssignedID', $invoice->precedingInvoiceNumber);
            if (null !== $invoice->precedingInvoiceDate) {
                $this->dateTime($writer, 'ram:FormattedIssueDateTime', 'qdt:DateTimeString', $invoice->precedingInvoiceDate);
            }
            $writer->endElement();
        }

        $writer->endElement();
    }

    private function writeVatBreakdown(\XMLWriter $writer, VatBreakdown $breakdown): void
    {
        $writer->startElement('ram:ApplicableTradeTax');
        $this->element($writer, 'ram:CalculatedAmount', self::amount($breakdown->vat));
        $this->element($writer, 'ram:TypeCode', self::TAX_TYPE_VAT);
        $this->element($writer, 'ram:BasisAmount', self::amount($breakdown->base));
        $this->element($writer, 'ram:CategoryCode', $breakdown->vatCategory);
        $this->element($writer, 'ram:RateApplicablePercent', self::decimal($breakdown->vatRate, self::RATE_SCALE));
        $writer->endElement();
    }

    private function writeAllowance(\XMLWriter $writer, TransmittableAllowance $allowance): void
    {
        $writer->startElement('ram:SpecifiedTradeAllowanceCharge');
        $writer->startElement('ram:ChargeIndicator');
        $this->element($writer, 'udt:Indicator', 'false');
        $writer->endElement();
        $this->element($writer, 'ram:ActualAmount', self::amount($allowance->amount));
        $this->element($writer, 'ram:Reason', $allowance->reason);
        $this->categoryTax($writer, 'ram:CategoryTradeTax', $allowance->vatCategory, $allowance->vatRate);
        $writer->endElement();
    }

    /**
     * BG-4 (seller) / BG-7 (buyer).
     */
    private function party(\XMLWriter $writer, string $name, TransmittableParty $party, ?string $contactName, TransmittableElectronicAddress $electronicAddress): void
    {
        $writer->startElement($name);
        $this->element($writer, 'ram:Name', $party->name);

        $writer->startElement('ram:SpecifiedLegalOrganization');
        $this->element($writer, 'ram:ID', $party->siren, ['schemeID' => self::SCHEME_SIREN]);
        $writer->endElement();

        if (null !== $contactName) {
            $writer->startElement('ram:DefinedTradeContact');
            $this->element($writer, 'ram:PersonName', $contactName);
            $writer->endElement();
        }

        $this->address($writer, $party->address);

        $writer->startElement('ram:URIUniversalCommunication');
        $this->element($writer, 'ram:URIID', $electronicAddress->identifier, ['schemeID' => $electronicAddress->scheme]);
        $writer->endElement();

        if (null !== $party->vatNumber) {
            $writer->startElement('ram:SpecifiedTaxRegistration');
            $this->element($writer, 'ram:ID', $party->vatNumber, ['schemeID' => self::SCHEME_VAT]);
            $writer->endElement();
        }

        $writer->endElement();
    }

    /**
     * BG-5 / BG-8: up to three address lines; any further line is appended to
     * the third one.
     */
    private function address(\XMLWriter $writer, TransmittableAddress $address): void
    {
        $lines = array_values(array_filter($address->lines, static fn (string $line): bool => '' !== trim($line)));
        if (\count($lines) > 3) {
            $lines = [$lines[0], $lines[1], implode(', ', \array_slice($lines, 2))];
        }

        $writer->startElement('ram:PostalTradeAddress');
        if (null !== $address->postalCode) {
            $this->element($writer, 'ram:PostcodeCode', $address->postalCode);
        }
        foreach (['ram:LineOne', 'ram:LineTwo', 'ram:LineThree'] as $index => $element) {
            if (isset($lines[$index])) {
                $this->element($writer, $element, $lines[$index]);
            }
        }
        if (null !== $address->city) {
            $this->element($writer, 'ram:CityName', $address->city);
        }
        $this->element($writer, 'ram:CountryID', $address->countryCode);
        $writer->endElement();
    }

    private function categoryTax(\XMLWriter $writer, string $name, string $category, string $rate): void
    {
        $writer->startElement($name);
        $this->element($writer, 'ram:TypeCode', self::TAX_TYPE_VAT);
        $this->element($writer, 'ram:CategoryCode', $category);
        $this->element($writer, 'ram:RateApplicablePercent', self::decimal($rate, self::RATE_SCALE));
        $writer->endElement();
    }

    private function dateTime(\XMLWriter $writer, string $name, string $stringElement, \DateTimeImmutable $date): void
    {
        $writer->startElement($name);
        $this->element($writer, $stringElement, $date->format('Ymd'), ['format' => self::DATE_FORMAT]);
        $writer->endElement();
    }

    /**
     * @param array<string, string> $attributes
     */
    private function element(\XMLWriter $writer, string $name, string $value, array $attributes = []): void
    {
        $writer->startElement($name);
        foreach ($attributes as $attribute => $attributeValue) {
            $writer->writeAttribute($attribute, $attributeValue);
        }
        $writer->text($value);
        $writer->endElement();
    }

    /**
     * Integer minor units → "1234.56", without going through a float.
     */
    private static function amount(int $minor): string
    {
        $digits = str_pad(ltrim((string) $minor, '-'), 3, '0', \STR_PAD_LEFT);

        return ($minor < 0 ? '-' : '') . substr($digits, 0, -2) . '.' . substr($digits, -2);
    }

    /**
     * Pads a decimal string to at least $scale fraction digits ("20" →
     * "20.00"); never rounds. A value that is not a plain decimal is written
     * as is and left to the XSD.
     */
    private static function decimal(string $value, int $scale): string
    {
        if (1 !== preg_match('/^(-?\d+)(?:\.(\d*))?$/', $value, $matches)) {
            return $value;
        }

        $fraction = $matches[2] ?? '';

        return $matches[1] . '.' . str_pad($fraction, max($scale, \strlen($fraction)), '0');
    }
}
