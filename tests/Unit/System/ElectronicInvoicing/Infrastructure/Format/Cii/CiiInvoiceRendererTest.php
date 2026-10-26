<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Infrastructure\Format\Cii;

use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableAddress;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableAllowance;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableElectronicAddress;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableInvoice;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableLine;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableNote;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableParty;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\VatBreakdown;
use App\System\ElectronicInvoicing\Application\Exception\RenderingFailedException;
use App\System\ElectronicInvoicing\Infrastructure\Format\Cii\CiiInvoiceRenderer;
use App\System\ElectronicInvoicing\Infrastructure\Format\XsdValidator;
use App\Tests\Unit\System\ElectronicInvoicing\Infrastructure\Format\AfnorSamplesXsdTest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Golden files live in tests/Fixtures/electronic-invoicing/cii. After an
 * intended change of the rendering, regenerate them with
 * UPDATE_GOLDEN_FILES=1 and review the diff before committing.
 */
final class CiiInvoiceRendererTest extends TestCase
{
    private const string GOLDEN_DIR = __DIR__ . '/../../../../../../Fixtures/electronic-invoicing/cii';
    private const string AFNOR_UC1  = AfnorSamplesXsdTest::AFNOR . '/UC1/UC1_F202500003_00-INV_20250701_CII_EN16931.xml';

    private const array NAMESPACES = [
        'rsm' => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
        'ram' => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
        'qdt' => 'urn:un:unece:uncefact:data:standard:QualifiedDataType:100',
        'udt' => 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100',
    ];

    private const string AGREEMENT  = '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement';
    private const string SETTLEMENT = '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement';
    private const string LINES      = '/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem';

    private CiiInvoiceRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new CiiInvoiceRenderer(new XsdValidator(), AfnorSamplesXsdTest::SCHEMA);
    }

    #[DataProvider('provideRendersTheGoldenFileCases')]
    public function testRendersTheGoldenFile(string $name, TransmittableInvoice $invoice): void
    {
        $xml    = $this->renderer->render($invoice, self::buyerAddress());
        $golden = self::GOLDEN_DIR . '/' . $name . '.xml';

        if ('1' === getenv('UPDATE_GOLDEN_FILES')) {
            file_put_contents($golden, $xml);
        }

        self::assertFileExists($golden);
        self::assertSame(file_get_contents($golden), $xml);
    }

    #[DataProvider('provideGoldenFilePassesTheXsdCases')]
    public function testGoldenFilePassesTheXsd(string $name): void
    {
        $xml = file_get_contents(self::GOLDEN_DIR . '/' . $name . '.xml');
        self::assertIsString($xml);

        self::assertSame([], new XsdValidator()->validate($xml, AfnorSamplesXsdTest::SCHEMA));
    }

    /**
     * @return iterable<string, array{string, TransmittableInvoice}>
     */
    public static function provideRendersTheGoldenFileCases(): iterable
    {
        yield 's2-nominal' => ['s2-nominal', self::s2Nominal()];
        yield 'b1-due-date' => ['b1-due-date', self::b1DueDate()];
        yield 'm1-discount' => ['m1-discount', self::m1Discount()];
        yield 'credit-note-381' => ['credit-note-381', self::creditNote()];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideGoldenFilePassesTheXsdCases(): iterable
    {
        foreach (self::provideRendersTheGoldenFileCases() as $key => [$name]) {
            yield $key => [$name];
        }
    }

    public function testCarriesTheContextAndTheHeader(): void
    {
        $xpath = $this->render(self::s2Nominal());

        self::assertSame('S2', self::text($xpath, '/rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext/ram:BusinessProcessSpecifiedDocumentContextParameter/ram:ID'));
        self::assertSame('urn:cen.eu:en16931:2017', self::text($xpath, '/rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID'));
        self::assertSame('F2026-000042', self::text($xpath, '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:ID'));
        self::assertSame('380', self::text($xpath, '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:TypeCode'));
        self::assertSame('20261020', self::text($xpath, "/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:IssueDateTime/udt:DateTimeString[@format='102']"));
        self::assertSame('20261015', self::text($xpath, "/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeDelivery/ram:ActualDeliverySupplyChainEvent/ram:OccurrenceDateTime/udt:DateTimeString[@format='102']"));
    }

    public function testCarriesThePaymentNotes(): void
    {
        $xpath = $this->render(self::s2Nominal());

        foreach (['PMD', 'PMT', 'AAB'] as $subject) {
            self::assertSame(1, self::nodeCount($xpath, "/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:IncludedNote[ram:SubjectCode='" . $subject . "']/ram:Content"), $subject);
        }
        self::assertSame(
            'Indemnité forfaitaire pour frais de recouvrement : 40 €.',
            self::text($xpath, "/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:IncludedNote[ram:SubjectCode='PMT']/ram:Content"),
        );
    }

    public function testCarriesSellerAndBuyerIdentifiersAndElectronicAddresses(): void
    {
        $xpath = $this->render(self::s2Nominal());

        self::assertSame('Clinique Vétérinaire des Tilleuls', self::text($xpath, self::AGREEMENT . '/ram:SellerTradeParty/ram:Name'));
        self::assertSame('100000009', self::text($xpath, self::AGREEMENT . "/ram:SellerTradeParty/ram:SpecifiedLegalOrganization/ram:ID[@schemeID='0002']"));
        self::assertSame('FR88100000009', self::text($xpath, self::AGREEMENT . "/ram:SellerTradeParty/ram:SpecifiedTaxRegistration/ram:ID[@schemeID='VA']"));
        self::assertSame('100000009', self::text($xpath, self::AGREEMENT . "/ram:SellerTradeParty/ram:URIUniversalCommunication/ram:URIID[@schemeID='0225']"));
        self::assertSame('69003', self::text($xpath, self::AGREEMENT . '/ram:SellerTradeParty/ram:PostalTradeAddress/ram:PostcodeCode'));

        self::assertSame('Élevage du Grand Pré', self::text($xpath, self::AGREEMENT . '/ram:BuyerTradeParty/ram:Name'));
        self::assertSame('200000008', self::text($xpath, self::AGREEMENT . "/ram:BuyerTradeParty/ram:SpecifiedLegalOrganization/ram:ID[@schemeID='0002']"));
        self::assertSame('FR37200000008', self::text($xpath, self::AGREEMENT . "/ram:BuyerTradeParty/ram:SpecifiedTaxRegistration/ram:ID[@schemeID='VA']"));
        self::assertSame('Mme Martin', self::text($xpath, self::AGREEMENT . '/ram:BuyerTradeParty/ram:DefinedTradeContact/ram:PersonName'));
    }

    public function testBuyerElectronicAddressIsTheOnePassedIn(): void
    {
        $xpath = $this->render(self::s2Nominal(), new TransmittableElectronicAddress('0088', '3654789851'));

        self::assertSame('3654789851', self::text($xpath, self::AGREEMENT . "/ram:BuyerTradeParty/ram:URIUniversalCommunication/ram:URIID[@schemeID='0088']"));
        self::assertSame(1, self::nodeCount($xpath, self::AGREEMENT . '/ram:BuyerTradeParty/ram:URIUniversalCommunication/ram:URIID'));
    }

    public function testCarriesTheLines(): void
    {
        $xpath = $this->render(self::b1DueDate());

        self::assertSame(2, self::nodeCount($xpath, self::LINES));
        self::assertSame('2', self::text($xpath, self::LINES . '[2]/ram:AssociatedDocumentLineDocument/ram:LineID'));
        self::assertSame('Antiparasitaire', self::text($xpath, self::LINES . '[2]/ram:SpecifiedTradeProduct/ram:Name'));
        self::assertSame('3', self::text($xpath, self::LINES . "[2]/ram:SpecifiedLineTradeDelivery/ram:BilledQuantity[@unitCode='C62']"));
        self::assertSame('12.596667', self::text($xpath, self::LINES . '[2]/ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:ChargeAmount'));
        self::assertSame('52.750000', self::text($xpath, self::LINES . '[1]/ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:ChargeAmount'));
        self::assertSame(0, self::nodeCount($xpath, self::LINES . '/ram:SpecifiedLineTradeAgreement/ram:GrossPriceProductTradePrice'));
        self::assertSame('37.79', self::text($xpath, self::LINES . '[2]/ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount'));
        self::assertSame('S', self::text($xpath, self::LINES . "[2]/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax[ram:TypeCode='VAT']/ram:CategoryCode"));
        self::assertSame('10.00', self::text($xpath, self::LINES . '[2]/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:RateApplicablePercent'));
    }

    public function testCarriesOneVatBreakdownPerRateAndThePaymentTerms(): void
    {
        $xpath = $this->render(self::b1DueDate());

        self::assertSame('B1', self::text($xpath, '/rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext/ram:BusinessProcessSpecifiedDocumentContextParameter/ram:ID'));
        self::assertSame(2, self::nodeCount($xpath, self::SETTLEMENT . '/ram:ApplicableTradeTax'));
        self::assertSame('31.65', self::text($xpath, self::SETTLEMENT . "/ram:ApplicableTradeTax[ram:RateApplicablePercent='20.00']/ram:CalculatedAmount"));
        self::assertSame('158.25', self::text($xpath, self::SETTLEMENT . "/ram:ApplicableTradeTax[ram:RateApplicablePercent='20.00']/ram:BasisAmount"));
        self::assertSame('3.78', self::text($xpath, self::SETTLEMENT . "/ram:ApplicableTradeTax[ram:RateApplicablePercent='10.00']/ram:CalculatedAmount"));
        self::assertSame('37.79', self::text($xpath, self::SETTLEMENT . "/ram:ApplicableTradeTax[ram:RateApplicablePercent='10.00']/ram:BasisAmount"));
        self::assertSame(2, self::nodeCount($xpath, self::SETTLEMENT . "/ram:ApplicableTradeTax[ram:TypeCode='VAT'][ram:CategoryCode='S']"));
        self::assertSame(0, self::nodeCount($xpath, self::SETTLEMENT . '/ram:ApplicableTradeTax/ram:DueDateTypeCode'));

        self::assertSame('Paiement à 30 jours fin de mois', self::text($xpath, self::SETTLEMENT . '/ram:SpecifiedTradePaymentTerms/ram:Description'));
        self::assertSame('20261130', self::text($xpath, self::SETTLEMENT . "/ram:SpecifiedTradePaymentTerms/ram:DueDateDateTime/udt:DateTimeString[@format='102']"));
    }

    public function testCarriesTheDocumentLevelAllowancesAndTheTotals(): void
    {
        $xpath = $this->render(self::m1Discount());

        $allowances = self::SETTLEMENT . '/ram:SpecifiedTradeAllowanceCharge';
        self::assertSame(2, self::nodeCount($xpath, $allowances));
        self::assertSame('false', self::text($xpath, $allowances . '[1]/ram:ChargeIndicator/udt:Indicator'));
        self::assertSame('6.00', self::text($xpath, $allowances . '[1]/ram:ActualAmount'));
        self::assertSame('Remise', self::text($xpath, $allowances . '[1]/ram:Reason'));
        self::assertSame('20.00', self::text($xpath, $allowances . "[1]/ram:CategoryTradeTax[ram:TypeCode='VAT'][ram:CategoryCode='S']/ram:RateApplicablePercent"));
        self::assertSame('2.90', self::text($xpath, $allowances . '[2]/ram:ActualAmount'));
        self::assertSame('10.00', self::text($xpath, $allowances . '[2]/ram:CategoryTradeTax/ram:RateApplicablePercent'));

        $totals = self::SETTLEMENT . '/ram:SpecifiedTradeSettlementHeaderMonetarySummation';
        self::assertSame('89.00', self::text($xpath, $totals . '/ram:LineTotalAmount'));
        self::assertSame('8.90', self::text($xpath, $totals . '/ram:AllowanceTotalAmount'));
        self::assertSame('80.10', self::text($xpath, $totals . '/ram:TaxBasisTotalAmount'));
        self::assertSame('13.41', self::text($xpath, $totals . "/ram:TaxTotalAmount[@currencyID='EUR']"));
        self::assertSame('93.51', self::text($xpath, $totals . '/ram:GrandTotalAmount'));
        self::assertSame('20.00', self::text($xpath, $totals . '/ram:TotalPrepaidAmount'));
        self::assertSame('73.51', self::text($xpath, $totals . '/ram:DuePayableAmount'));
    }

    public function testCreditNoteReferencesThePrecedingInvoice(): void
    {
        $xpath = $this->render(self::creditNote());

        self::assertSame('381', self::text($xpath, '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:TypeCode'));
        self::assertSame('F2026-000042', self::text($xpath, self::SETTLEMENT . '/ram:InvoiceReferencedDocument/ram:IssuerAssignedID'));
        self::assertSame('20261020', self::text($xpath, self::SETTLEMENT . "/ram:InvoiceReferencedDocument/ram:FormattedIssueDateTime/qdt:DateTimeString[@format='102']"));
        self::assertSame(0, self::nodeCount($xpath, self::SETTLEMENT . '/ram:SpecifiedTradePaymentTerms'));
        self::assertSame(0, self::nodeCount($xpath, '//ram:ApplicableHeaderTradeDelivery/*'));
        self::assertSame(0, self::nodeCount($xpath, self::AGREEMENT . '/ram:BuyerTradeParty/ram:SpecifiedTaxRegistration'));
        self::assertSame(0, self::nodeCount($xpath, self::AGREEMENT . '/ram:BuyerTradeParty/ram:DefinedTradeContact'));
    }

    public function testInvoiceCarriesNoPrecedingInvoiceReference(): void
    {
        $xpath = $this->render(self::s2Nominal());

        self::assertSame(0, self::nodeCount($xpath, self::SETTLEMENT . '/ram:InvoiceReferencedDocument'));
    }

    public function testPrecedingInvoiceDateIsOptional(): void
    {
        $xpath = $this->render(self::creditNote(precedingInvoiceDate: null));

        self::assertSame('F2026-000042', self::text($xpath, self::SETTLEMENT . '/ram:InvoiceReferencedDocument/ram:IssuerAssignedID'));
        self::assertSame(0, self::nodeCount($xpath, self::SETTLEMENT . '/ram:InvoiceReferencedDocument/ram:FormattedIssueDateTime'));
    }

    public function testAddressKeepsThreeLinesAndSkipsMissingParts(): void
    {
        $xpath = $this->render(self::m1Discount());

        $address = self::AGREEMENT . '/ram:BuyerTradeParty/ram:PostalTradeAddress';
        self::assertSame('Lieu-dit Le Grand Pré', self::text($xpath, $address . '/ram:LineOne'));
        self::assertSame('Bâtiment B', self::text($xpath, $address . '/ram:LineTwo'));
        self::assertSame('Chemin des Vignes, BP 12', self::text($xpath, $address . '/ram:LineThree'));
        self::assertSame(0, self::nodeCount($xpath, $address . '/ram:PostcodeCode'));
        self::assertSame(0, self::nodeCount($xpath, $address . '/ram:CityName'));
        self::assertSame('FR', self::text($xpath, $address . '/ram:CountryID'));
    }

    public function testAmountsAreWrittenFromMinorUnitsWithTwoDecimals(): void
    {
        $xpath = $this->render(self::s2Nominal(paidAmount: 7, duePayable: -1234, grossTotal: 123456789012345));

        $totals = self::SETTLEMENT . '/ram:SpecifiedTradeSettlementHeaderMonetarySummation';
        self::assertSame('0.07', self::text($xpath, $totals . '/ram:TotalPrepaidAmount'));
        self::assertSame('-12.34', self::text($xpath, $totals . '/ram:DuePayableAmount'));
        self::assertSame('1234567890123.45', self::text($xpath, $totals . '/ram:GrandTotalAmount'));
    }

    /**
     * The XSD types every amount, quantity and percentage as xsd:decimal: a
     * comma decimal separator is a schema violation.
     */
    public function testInvalidDocumentIsRefusedWithTheLibxmlMessages(): void
    {
        $invoice = self::s2Nominal(lines: [new TransmittableLine('1', 'Consultation', '1', 'C62', '45.000000', 4500, 'S', '20,0')]);

        try {
            $this->renderer->render($invoice, self::buyerAddress());
            self::fail('RenderingFailedException expected');
        } catch (RenderingFailedException $exception) {
            self::assertNotSame([], $exception->errors);
            self::assertStringContainsString('RateApplicablePercent', implode("\n", $exception->errors));
            self::assertStringContainsString("'20,0'", implode("\n", $exception->errors));
        }
    }

    /**
     * The AFNOR UC1 invoice, re-expressed as a TransmittableInvoice, renders
     * the same business values as the AFNOR sample (compared by XPath, not by
     * bytes: the sample carries optional terms Kiveto does not produce).
     */
    public function testAfnorUc1RendersTheSameBusinessValues(): void
    {
        $rendered = $this->render(self::afnorUc1(), new TransmittableElectronicAddress('0225', '200000008'));
        $sample   = file_get_contents(self::AFNOR_UC1);
        self::assertIsString($sample);
        $afnor = self::xpath($sample);

        $texts = [
            '/rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext/ram:BusinessProcessSpecifiedDocumentContextParameter/ram:ID',
            '/rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID',
            '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:ID',
            '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:TypeCode',
            '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:IssueDateTime/udt:DateTimeString',
            '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:IssueDateTime/udt:DateTimeString/@format',
            self::SETTLEMENT . '/ram:InvoiceCurrencyCode',
            self::SETTLEMENT . '/ram:ApplicableTradeTax/ram:TypeCode',
            self::SETTLEMENT . '/ram:ApplicableTradeTax/ram:CategoryCode',
            self::SETTLEMENT . '/ram:ApplicableTradeTax/ram:RateApplicablePercent',
            self::SETTLEMENT . '/ram:SpecifiedTradePaymentTerms/ram:Description',
            self::SETTLEMENT . '/ram:SpecifiedTradePaymentTerms/ram:DueDateDateTime/udt:DateTimeString',
        ];
        for ($i = 1; $i <= 6; ++$i) {
            $texts[] = '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:IncludedNote[' . $i . ']/ram:Content';
            $texts[] = '/rsm:CrossIndustryInvoice/rsm:ExchangedDocument/ram:IncludedNote[' . $i . ']/ram:SubjectCode';
        }
        foreach (['Seller', 'Buyer'] as $role) {
            $party      = self::AGREEMENT . '/ram:' . $role . 'TradeParty';
            $partyPaths = [
                'ram:Name', 'ram:SpecifiedLegalOrganization/ram:ID', 'ram:SpecifiedLegalOrganization/ram:ID/@schemeID',
                'ram:PostalTradeAddress/ram:PostcodeCode', 'ram:PostalTradeAddress/ram:LineOne', 'ram:PostalTradeAddress/ram:LineTwo',
                'ram:PostalTradeAddress/ram:CityName', 'ram:PostalTradeAddress/ram:CountryID',
                'ram:URIUniversalCommunication/ram:URIID', 'ram:URIUniversalCommunication/ram:URIID/@schemeID',
                'ram:SpecifiedTaxRegistration/ram:ID', 'ram:SpecifiedTaxRegistration/ram:ID/@schemeID',
            ];
            foreach ($partyPaths as $path) {
                $texts[] = $party . '/' . $path;
            }
        }
        foreach ([1, 2] as $i) {
            $line    = self::LINES . '[' . $i . ']';
            $texts[] = $line . '/ram:AssociatedDocumentLineDocument/ram:LineID';
            $texts[] = $line . '/ram:SpecifiedTradeProduct/ram:Name';
            $texts[] = $line . '/ram:SpecifiedLineTradeDelivery/ram:BilledQuantity';
            $texts[] = $line . '/ram:SpecifiedLineTradeDelivery/ram:BilledQuantity/@unitCode';
            $texts[] = $line . '/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:CategoryCode';
            $texts[] = $line . '/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:RateApplicablePercent';
            $texts[] = $line . '/ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount';
        }

        $numbers = [
            self::LINES . '[1]/ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:ChargeAmount',
            self::LINES . '[2]/ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:ChargeAmount',
        ];
        $amounts = [
            self::SETTLEMENT . '/ram:ApplicableTradeTax/ram:CalculatedAmount',
            self::SETTLEMENT . '/ram:ApplicableTradeTax/ram:BasisAmount',
        ];
        foreach (['LineTotalAmount', 'AllowanceTotalAmount', 'TaxBasisTotalAmount', 'TaxTotalAmount', 'TaxTotalAmount/@currencyID', 'GrandTotalAmount', 'TotalPrepaidAmount', 'DuePayableAmount'] as $total) {
            $amounts[] = self::SETTLEMENT . '/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:' . $total;
        }

        foreach ([...$texts, ...$amounts] as $path) {
            $expected = self::text($afnor, $path);
            self::assertNotSame('', $expected, $path);
            self::assertSame($expected, self::text($rendered, $path), $path);
        }
        foreach ($numbers as $path) {
            // The sample carries the unit price with 4 decimals, Kiveto with 6.
            self::assertSame(self::number($afnor, $path), self::number($rendered, $path), $path);
        }
    }

    private function render(TransmittableInvoice $invoice, ?TransmittableElectronicAddress $address = null): \DOMXPath
    {
        return self::xpath($this->renderer->render($invoice, $address ?? self::buyerAddress()));
    }

    private static function xpath(string $xml): \DOMXPath
    {
        $document = new \DOMDocument();
        self::assertTrue($document->loadXML($xml));
        $xpath = new \DOMXPath($document);
        foreach (self::NAMESPACES as $prefix => $namespace) {
            $xpath->registerNamespace($prefix, $namespace);
        }

        return $xpath;
    }

    private static function text(\DOMXPath $xpath, string $path): string
    {
        $value = $xpath->evaluate('string(' . $path . ')');
        self::assertIsString($value);

        return $value;
    }

    private static function number(\DOMXPath $xpath, string $path): float
    {
        $value = $xpath->evaluate('number(' . $path . ')');
        self::assertIsFloat($value);

        return $value;
    }

    private static function nodeCount(\DOMXPath $xpath, string $path): int
    {
        $nodes = $xpath->query($path);
        self::assertInstanceOf(\DOMNodeList::class, $nodes);

        return $nodes->length;
    }

    private static function buyerAddress(): TransmittableElectronicAddress
    {
        return new TransmittableElectronicAddress('0225', '200000008_FACTURES');
    }

    private static function seller(): TransmittableParty
    {
        return new TransmittableParty(
            name: 'Clinique Vétérinaire des Tilleuls',
            siren: '100000009',
            vatNumber: 'FR88100000009',
            address: new TransmittableAddress(['12 rue des Tilleuls'], '69003', 'Lyon', 'FR'),
            electronicAddress: new TransmittableElectronicAddress('0225', '100000009'),
        );
    }

    private static function buyer(?string $vatNumber = 'FR37200000008', ?TransmittableAddress $address = null): TransmittableParty
    {
        return new TransmittableParty(
            name: 'Élevage du Grand Pré',
            siren: '200000008',
            vatNumber: $vatNumber,
            address: $address ?? new TransmittableAddress(['Lieu-dit Le Grand Pré'], '01250', 'Ceyzériat', 'FR'),
            electronicAddress: new TransmittableElectronicAddress('0225', '200000008'),
        );
    }

    /**
     * @return list<TransmittableNote>
     */
    private static function legalNotes(): array
    {
        return [
            new TransmittableNote('PMD', 'Tout retard de paiement entraîne des pénalités au taux de trois fois le taux d\'intérêt légal.'),
            new TransmittableNote('PMT', 'Indemnité forfaitaire pour frais de recouvrement : 40 €.'),
            new TransmittableNote('AAB', 'Pas d\'escompte pour paiement anticipé.'),
        ];
    }

    /**
     * Services only (S2): 1 × 45,00 + 2 × 32,50 at 20 %.
     *
     * @param list<TransmittableLine>|null $lines
     */
    private static function s2Nominal(?array $lines = null, int $paidAmount = 0, int $duePayable = 13200, int $grossTotal = 13200): TransmittableInvoice
    {
        return new TransmittableInvoice(
            documentId: '01936a52-7c1e-7d3a-9f00-000000000042',
            kind: TransmittableInvoice::KIND_INVOICE,
            number: 'F2026-000042',
            issueDate: new \DateTimeImmutable('2026-10-20'),
            typeCode: '380',
            invoicingFramework: 'S2',
            processingRule: 'B2B',
            currency: 'EUR',
            seller: self::seller(),
            buyer: self::buyer(),
            buyerContact: 'Mme Martin',
            deliveryDate: new \DateTimeImmutable('2026-10-15'),
            dueDate: null,
            paymentTermsNote: 'Paiement à réception',
            lineTotal: 11000,
            allowanceTotal: 0,
            taxBasis: 11000,
            vatTotal: 2200,
            grossTotal: $grossTotal,
            paidAmount: $paidAmount,
            duePayable: $duePayable,
            lines: $lines ?? [
                new TransmittableLine('1', 'Consultation', '1', 'C62', '45.000000', 4500, 'S', '20'),
                new TransmittableLine('2', 'Vaccin CHPPiL', '2', 'C62', '32.500000', 6500, 'S', '20'),
            ],
            allowances: [],
            vatBreakdown: [new VatBreakdown('S', '20', 11000, 2200)],
            notes: self::legalNotes(),
        );
    }

    /**
     * Goods (B1) with a due date: 3 × 52,75 at 20 % + 3 × 12,596667 at 10 %.
     */
    private static function b1DueDate(): TransmittableInvoice
    {
        return new TransmittableInvoice(
            documentId: '01936a52-7c1e-7d3a-9f00-000000000043',
            kind: TransmittableInvoice::KIND_INVOICE,
            number: 'F2026-000043',
            issueDate: new \DateTimeImmutable('2026-10-20'),
            typeCode: '380',
            invoicingFramework: 'B1',
            processingRule: 'B2B',
            currency: 'EUR',
            seller: self::seller(),
            buyer: self::buyer(),
            buyerContact: null,
            deliveryDate: new \DateTimeImmutable('2026-10-20'),
            dueDate: new \DateTimeImmutable('2026-11-30'),
            paymentTermsNote: 'Paiement à 30 jours fin de mois',
            lineTotal: 19604,
            allowanceTotal: 0,
            taxBasis: 19604,
            vatTotal: 3543,
            grossTotal: 23147,
            paidAmount: 0,
            duePayable: 23147,
            lines: [
                new TransmittableLine('1', 'Croquettes 10 kg', '3', 'C62', '52.750000', 15825, 'S', '20'),
                new TransmittableLine('2', 'Antiparasitaire', '3', 'C62', '12.596667', 3779, 'S', '10'),
            ],
            allowances: [],
            vatBreakdown: [
                new VatBreakdown('S', '20', 15825, 3165),
                new VatBreakdown('S', '10', 3779, 378),
            ],
            notes: self::legalNotes(),
        );
    }

    /**
     * Mixed (M1) with a 10 % discount split per rate and a deposit already
     * paid.
     */
    private static function m1Discount(): TransmittableInvoice
    {
        return new TransmittableInvoice(
            documentId: '01936a52-7c1e-7d3a-9f00-000000000044',
            kind: TransmittableInvoice::KIND_INVOICE,
            number: 'F2026-000044',
            issueDate: new \DateTimeImmutable('2026-10-20'),
            typeCode: '380',
            invoicingFramework: 'M1',
            processingRule: 'B2B',
            currency: 'EUR',
            seller: self::seller(),
            buyer: self::buyer(address: new TransmittableAddress(['Lieu-dit Le Grand Pré', '', 'Bâtiment B', 'Chemin des Vignes', 'BP 12'], null, null, 'FR')),
            buyerContact: null,
            deliveryDate: new \DateTimeImmutable('2026-10-18'),
            dueDate: new \DateTimeImmutable('2026-11-19'),
            paymentTermsNote: 'Paiement à 30 jours',
            lineTotal: 8900,
            allowanceTotal: 890,
            taxBasis: 8010,
            vatTotal: 1341,
            grossTotal: 9351,
            paidAmount: 2000,
            duePayable: 7351,
            lines: [
                new TransmittableLine('1', 'Consultation', '1', 'C62', '60.000000', 6000, 'S', '20'),
                new TransmittableLine('2', 'Vermifuge', '4', 'C62', '7.250000', 2900, 'S', '10'),
            ],
            allowances: [
                new TransmittableAllowance(600, 'S', '20', 'Remise'),
                new TransmittableAllowance(290, 'S', '10', 'Remise'),
            ],
            vatBreakdown: [
                new VatBreakdown('S', '20', 5400, 1080),
                new VatBreakdown('S', '10', 2610, 261),
            ],
            notes: self::legalNotes(),
        );
    }

    /**
     * Credit note (381) on the S2 invoice: the consultation is credited.
     */
    private static function creditNote(?\DateTimeImmutable $precedingInvoiceDate = new \DateTimeImmutable('2026-10-20')): TransmittableInvoice
    {
        return new TransmittableInvoice(
            documentId: '01936a52-7c1e-7d3a-9f00-000000000045',
            kind: TransmittableInvoice::KIND_CREDIT_NOTE,
            number: 'A2026-000007',
            issueDate: new \DateTimeImmutable('2026-10-21'),
            typeCode: '381',
            invoicingFramework: 'S2',
            processingRule: 'B2B',
            currency: 'EUR',
            seller: self::seller(),
            buyer: self::buyer(vatNumber: null),
            buyerContact: null,
            deliveryDate: null,
            dueDate: null,
            paymentTermsNote: null,
            lineTotal: 4500,
            allowanceTotal: 0,
            taxBasis: 4500,
            vatTotal: 900,
            grossTotal: 5400,
            paidAmount: 0,
            duePayable: 5400,
            lines: [new TransmittableLine('1', 'Consultation', '1', 'C62', '45.000000', 4500, 'S', '20.00')],
            allowances: [],
            vatBreakdown: [new VatBreakdown('S', '20.00', 4500, 900)],
            notes: [],
            precedingInvoiceNumber: 'F2026-000042',
            precedingInvoiceDate: $precedingInvoiceDate,
        );
    }

    /**
     * docs/e-invoicing AFNOR UC1 (F202500003), restricted to what Kiveto
     * produces.
     */
    private static function afnorUc1(): TransmittableInvoice
    {
        return new TransmittableInvoice(
            documentId: '01936a52-7c1e-7d3a-9f00-000000000003',
            kind: TransmittableInvoice::KIND_INVOICE,
            number: 'F202500003',
            issueDate: new \DateTimeImmutable('2025-07-01'),
            typeCode: '380',
            invoicingFramework: 'S1',
            processingRule: 'B2B',
            currency: 'EUR',
            seller: new TransmittableParty(
                name: 'LE VENDEUR',
                siren: '100000009',
                vatNumber: 'FR88100000009',
                address: new TransmittableAddress(['35 rue d\'ici', 'ligne 2 vendeur'], '75018', 'PARIS', 'FR'),
                electronicAddress: new TransmittableElectronicAddress('0225', '100000009_STATUTS'),
            ),
            buyer: new TransmittableParty(
                name: 'LE CLIENT',
                siren: '200000008',
                vatNumber: 'FR37200000008',
                address: new TransmittableAddress(['55 avenue de là-bas', 'acheteur ligne 2'], '06000', 'MA VILLE', 'FR'),
                electronicAddress: new TransmittableElectronicAddress('0225', '200000008'),
            ),
            buyerContact: null,
            deliveryDate: null,
            dueDate: new \DateTimeImmutable('2025-07-31'),
            paymentTermsNote: 'PAIEMENT 30 JOURS NET',
            lineTotal: 1000000,
            allowanceTotal: 0,
            taxBasis: 1000000,
            vatTotal: 200000,
            grossTotal: 1200000,
            paidAmount: 0,
            duePayable: 1200000,
            lines: [
                new TransmittableLine('1', 'SERVICE_FOURNI1', '200.0000', 'HUR', '40.000000', 800000, 'S', '20.00'),
                new TransmittableLine('2', 'SERVICE_FOURNI2', '5.0000', 'DAY', '400.000000', 200000, 'S', '20.00'),
            ],
            allowances: [],
            vatBreakdown: [new VatBreakdown('S', '20.00', 1000000, 200000)],
            notes: [
                new TransmittableNote('REG', 'VENDEUR SARL au capital de 50 000 EUR'),
                new TransmittableNote('ABL', 'RCS MAVILLE 100 000 009'),
                new TransmittableNote('AAI', '35 ma rue a moi,75018 PARIS, FR – contact@vendeur.fr - www.levendeur.fr  – N° TVA : FR88 100 000 009'),
                new TransmittableNote('PMD', 'Tout retard de paiement engendre une pénalité exigible à compter de la date d\'échéance, calculée sur la base de trois fois le taux d\'intérêt légal. '),
                new TransmittableNote('PMT', 'Indemnité forfaitaire pour frais de recouvrement en cas de retard de paiement : 40 €.'),
                new TransmittableNote('AAB', 'Les réglements reçus avant la date d\'échéance ne donneront pas lieu à escompte.'),
            ],
        );
    }
}
