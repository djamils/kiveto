<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Application\Command;

use App\Context\Billing\Application\Command\CloseCashDesk\CloseCashDesk;
use App\Context\Billing\Application\Command\CloseCashDesk\CloseCashDeskHandler;
use App\Context\Billing\Application\Command\DepositPendingCheques\DepositPendingCheques;
use App\Context\Billing\Application\Command\DepositPendingCheques\DepositPendingChequesHandler;
use App\Context\Billing\Application\Command\IssueCreditNote\IssueCreditNote;
use App\Context\Billing\Application\Command\IssueCreditNote\IssueCreditNoteHandler;
use App\Context\Billing\Application\Command\RecordCounterSale\ClientTaxProfileInput;
use App\Context\Billing\Application\Command\RecordCounterSale\CounterSaleNewClient;
use App\Context\Billing\Application\Command\RecordCounterSale\RecordCounterSale;
use App\Context\Billing\Application\Command\RecordCounterSale\RecordCounterSaleHandler;
use App\Context\Billing\Application\Port\CashDeskReadRepositoryInterface;
use App\Context\Billing\Application\Port\CounterArticleDto;
use App\Context\Billing\Application\Port\CounterCatalogInterface;
use App\Context\Billing\Application\Port\CounterClientRegistryInterface;
use App\Context\Billing\Application\Port\PayerDirectoryInterface;
use App\Context\Billing\Application\Port\PayerSnapshotDto;
use App\Context\Billing\Application\Port\ResolvedTaxDto;
use App\Context\Billing\Application\Port\TaxResolverInterface;
use App\Context\Billing\Application\Query\GetCashDesk\CashClosingView;
use App\Context\Billing\Application\Query\GetCashDesk\ChequeView;
use App\Context\Billing\Application\Query\GetCashDesk\TakingsView;
use App\Context\Billing\Application\Service\InvoiceLineFactory;
use App\Context\Billing\Domain\CashClosing;
use App\Context\Billing\Domain\ChequeDeposit;
use App\Context\Billing\Domain\CreditNote;
use App\Context\Billing\Domain\Exception\CounterClientAlreadyKnownException;
use App\Context\Billing\Domain\Exception\CounterClientNotReadBackException;
use App\Context\Billing\Domain\Exception\InvalidCashClosingException;
use App\Context\Billing\Domain\Exception\InvalidChequeDepositException;
use App\Context\Billing\Domain\Exception\InvalidCounterClientException;
use App\Context\Billing\Domain\Exception\InvalidCounterClientTaxProfileException;
use App\Context\Billing\Domain\Exception\InvalidCreditNoteException;
use App\Context\Billing\Domain\Exception\InvalidInvoiceLineException;
use App\Context\Billing\Domain\Exception\InvalidPayerException;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\Repository\CashClosingRepositoryInterface;
use App\Context\Billing\Domain\Repository\ChequeDepositRepositoryInterface;
use App\Context\Billing\Domain\Repository\CreditNoteRepositoryInterface;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Domain\Service\CreditNoteCalculator;
use App\Context\Billing\Domain\Service\InvoiceNumberGeneratorInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\CounterSalePayer;
use App\Context\Billing\Domain\ValueObject\CreditNoteId;
use App\Context\Billing\Domain\ValueObject\Discount;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Context\Billing\Domain\ValueObject\InvoiceNumber;
use App\Context\Billing\Domain\ValueObject\InvoiceStatus;
use App\Context\Billing\Domain\ValueObject\OperationCategory;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\Payment;
use App\Context\Billing\Domain\ValueObject\PaymentId;
use App\Context\Billing\Domain\ValueObject\PaymentMethod;
use App\Context\Billing\Domain\ValueObject\PriceMode;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The desk's acts: credit notes, counter sales, cheque remittances and the
 * end-of-day closing.
 */
#[AllowMockObjectsWithoutExpectations]
final class DeskCommandHandlersTest extends TestCase
{
    private const string CLINIC_ID  = '01960000-0000-7000-8000-00000000f001';
    private const string INVOICE_ID = '01960000-0000-7000-8000-00000000f002';
    private const string LINE_A     = '01960000-0000-7000-8000-00000000f003';
    private const string LINE_B     = '01960000-0000-7000-8000-00000000f004';
    private const string ARTICLE    = '01960000-0000-7000-8000-00000000f005';
    private const string CLIENT     = '01960000-0000-7000-8000-00000000f006';
    private const string USER       = '01960000-0000-7000-8000-00000000f007';

    private int $ids = 100;

    // ── Credit notes ──

    public function testACreditNoteCreditsTheChosenLinesAndAppliesToTheInvoice(): void
    {
        $invoice = $this->issued();
        $invoice->recordPayment($this->payment(8400), $this->now());

        $saved = null;
        $notes = $this->createMock(CreditNoteRepositoryInterface::class);
        $notes->method('forInvoice')->willReturn([]);
        $notes->expects(self::once())->method('save')->willReturnCallback(static function (CreditNote $note) use (&$saved): void {
            $saved = $note;
        });

        $id = $this->creditHandler($invoice, $notes)(new IssueCreditNote(
            clinicId: self::CLINIC_ID,
            invoiceId: self::INVOICE_ID,
            lineIds: [self::LINE_B],
            reason: 'Retour produit',
            refundMethod: 'CARD',
            issuedByUserId: self::USER,
        ));

        self::assertInstanceOf(CreditNote::class, $saved);
        self::assertSame($saved->id()->toString(), $id);
        self::assertSame('AV-2026-000002', $saved->number()->toString());
        self::assertSame('FAC-2026-000009', $saved->invoiceNumber());
        self::assertSame(2400, $saved->totalGross()->minorUnits());
        self::assertSame([self::LINE_B], $saved->creditedLineIds());
        self::assertSame(PaymentMethod::CARD, $saved->refundMethod());
        self::assertSame(2400, $saved->refunded()->minorUnits());
        self::assertSame(InvoiceLineSource::CREDIT, $saved->lines()[0]->source());
        self::assertSame(2400, $invoice->amountCredited()->minorUnits());
        self::assertSame(InvoiceStatus::PAID, $invoice->status());
    }

    public function testADiscountedInvoiceCreditsWhatWasCharged(): void
    {
        $invoice = $this->draft();
        $invoice->applyDiscount(Discount::percentage('10', null), $this->now());
        $invoice->issue(InvoiceNumber::of(2026, 9), $this->now());

        $saved = null;
        $notes = $this->createMock(CreditNoteRepositoryInterface::class);
        $notes->method('forInvoice')->willReturn([]);
        $notes->method('save')->willReturnCallback(static function (CreditNote $note) use (&$saved): void {
            $saved = $note;
        });

        $this->creditHandler($invoice, $notes)(new IssueCreditNote(self::CLINIC_ID, self::INVOICE_ID, [self::LINE_B], 'Geste commercial'));

        self::assertInstanceOf(CreditNote::class, $saved);
        self::assertSame(1800, $saved->totalNet()->minorUnits());
        self::assertSame(360, $saved->totalTax()->minorUnits());
        // The line keeps its frozen net; the discount is credited per rate.
        self::assertSame(2000, $saved->lines()[0]->netAmount()->minorUnits());
        self::assertSame(200, $saved->discountAmount()->minorUnits());
        self::assertNull($saved->refundMethod());
        self::assertSame(InvoiceStatus::ISSUED, $invoice->status());
    }

    public function testTheLastCreditNoteTakesExactlyWhatIsLeft(): void
    {
        $invoice = $this->draft();
        $invoice->applyDiscount(Discount::percentage('33', null), $this->now());
        $invoice->issue(InvoiceNumber::of(2026, 9), $this->now());
        $previous = $this->previousNote($invoice, self::LINE_B, '01960000-0000-7000-8000-00000000f0aa');
        $invoice->credit($previous->totalGross(), false, $this->now());

        $saved = null;
        $notes = $this->createMock(CreditNoteRepositoryInterface::class);
        $notes->method('forInvoice')->willReturn([$previous]);
        $notes->method('save')->willReturnCallback(static function (CreditNote $note) use (&$saved): void {
            $saved = $note;
        });

        $this->creditHandler($invoice, $notes)(new IssueCreditNote(self::CLINIC_ID, self::INVOICE_ID, [self::LINE_A], 'Erreur de facturation'));

        self::assertInstanceOf(CreditNote::class, $saved);
        self::assertSame(InvoiceStatus::CREDITED, $invoice->status());
        self::assertSame(0, $invoice->creditableAmount()->minorUnits());
    }

    public function testALineIsCreditedOnce(): void
    {
        $invoice  = $this->issued();
        $previous = $this->previousNote($invoice, self::LINE_B, '01960000-0000-7000-8000-00000000f0ab');

        $notes = $this->createMock(CreditNoteRepositoryInterface::class);
        $notes->method('forInvoice')->willReturn([$previous]);
        $notes->expects(self::never())->method('save');

        $this->expectException(InvalidCreditNoteException::class);
        $this->expectExceptionMessage('already credited');

        $this->creditHandler($invoice, $notes)(new IssueCreditNote(self::CLINIC_ID, self::INVOICE_ID, [self::LINE_B], 'Erreur'));
    }

    public function testALineMustBeChosen(): void
    {
        $this->expectException(InvalidCreditNoteException::class);

        $this->creditHandler($this->issued(), $this->emptyNotes())(new IssueCreditNote(self::CLINIC_ID, self::INVOICE_ID, [], 'Erreur'));
    }

    public function testALineMustBelongToTheInvoice(): void
    {
        $this->expectException(InvalidCreditNoteException::class);
        $this->expectExceptionMessage('does not belong');

        $this->creditHandler($this->issued(), $this->emptyNotes())(new IssueCreditNote(self::CLINIC_ID, self::INVOICE_ID, [self::ARTICLE], 'Erreur'));
    }

    public function testADraftHasNothingToCredit(): void
    {
        $this->expectException(InvalidCreditNoteException::class);
        $this->expectExceptionMessage('Only an issued invoice');

        $this->creditHandler($this->draft(), $this->emptyNotes())(new IssueCreditNote(self::CLINIC_ID, self::INVOICE_ID, [self::LINE_A], 'Erreur'));
    }

    // ── Counter sale ──

    public function testACounterSaleIsIssuedAndPaidInOneGo(): void
    {
        $saved    = null;
        $invoices = $this->createMock(InvoiceRepositoryInterface::class);
        $invoices->expects(self::once())->method('save')->willReturnCallback(static function (Invoice $invoice) use (&$saved): void {
            $saved = $invoice;
        });

        $payers = $this->createMock(PayerDirectoryInterface::class);
        $payers->expects(self::once())->method('forClient')->with(self::CLIENT, self::CLINIC_ID)
            ->willReturn(new PayerSnapshotDto(self::CLIENT, 'Sophie Dupont', ['Paris']))
        ;

        $id = $this->saleHandler($invoices, $payers)(new RecordCounterSale(
            clinicId: self::CLINIC_ID,
            payerClientId: self::CLIENT,
            lines: [['articleId' => self::ARTICLE, 'quantity' => 2]],
            method: 'CASH',
            reference: null,
            recordedByUserId: self::USER,
        ));

        self::assertInstanceOf(Invoice::class, $saved);
        self::assertSame($saved->id()->toString(), $id);
        self::assertNull($saved->admissionId());
        self::assertSame('Sophie Dupont', $saved->payer()->displayName());
        self::assertSame(InvoiceStatus::PAID, $saved->status());
        self::assertSame('FAC-2026-000010', $saved->number()?->toString());
        self::assertSame(InvoiceLineSource::COUNTER, $saved->lines()[0]->source());
        self::assertSame(self::ARTICLE, $saved->lines()[0]->sourceReference());
        self::assertSame('2', $saved->lines()[0]->quantity());
        self::assertSame(4800, $saved->totalGross()->minorUnits());
        self::assertSame(PaymentMethod::CASH, $saved->payments()[0]->method());
        self::assertSame(self::USER, $saved->payments()[0]->recordedByUserId());
        self::assertSame(CounterSalePayer::CLIENT, $saved->counterSalePayer());
    }

    public function testAClientWhoRefusesToGiveContactDetailsBuysWithoutAName(): void
    {
        $saved    = null;
        $invoices = $this->createMock(InvoiceRepositoryInterface::class);
        $invoices->method('save')->willReturnCallback(static function (Invoice $invoice) use (&$saved): void {
            $saved = $invoice;
        });

        $payers = $this->createMock(PayerDirectoryInterface::class);
        $payers->expects(self::never())->method('forClient');

        $this->saleHandler($invoices, $payers)(new RecordCounterSale(self::CLINIC_ID, self::CLIENT, [['articleId' => self::ARTICLE, 'quantity' => 1]], 'CARD', contactDetailsRefused: true));

        self::assertInstanceOf(Invoice::class, $saved);
        self::assertSame('Vente sans coordonnées', $saved->payer()->displayName());
        self::assertNull($saved->payer()->clientId());
        self::assertSame(CounterSalePayer::REFUSED, $saved->counterSalePayer());
    }

    public function testASaleNamesItsClient(): void
    {
        $this->expectException(InvalidPayerException::class);

        $this->saleHandler($this->createStub(InvoiceRepositoryInterface::class), $this->createStub(PayerDirectoryInterface::class))(new RecordCounterSale(self::CLINIC_ID, null, [['articleId' => self::ARTICLE, 'quantity' => 1]], 'CARD'));
    }

    public function testAnotherClinicsClientIsRefused(): void
    {
        $payers = $this->createMock(PayerDirectoryInterface::class);
        $payers->method('forClient')->willReturn(null);

        $this->expectException(InvalidPayerException::class);

        $this->saleHandler($this->createStub(InvoiceRepositoryInterface::class), $payers)(new RecordCounterSale(self::CLINIC_ID, self::CLIENT, [['articleId' => self::ARTICLE, 'quantity' => 1]], 'CARD'));
    }

    public function testASnapshotWithoutAClientIdStillNamesThePayer(): void
    {
        $saved    = null;
        $invoices = $this->createMock(InvoiceRepositoryInterface::class);
        $invoices->method('save')->willReturnCallback(static function (Invoice $invoice) use (&$saved): void {
            $saved = $invoice;
        });

        $payers = $this->createMock(PayerDirectoryInterface::class);
        $payers->method('forClient')->willReturn(new PayerSnapshotDto(null, 'Sophie Dupont'));

        $this->saleHandler($invoices, $payers)(new RecordCounterSale(self::CLINIC_ID, self::CLIENT, [['articleId' => self::ARTICLE, 'quantity' => 1]], 'CARD'));

        self::assertInstanceOf(Invoice::class, $saved);
        self::assertNull($saved->payer()->clientId());
        self::assertSame(CounterSalePayer::CLIENT, $saved->counterSalePayer());
    }

    public function testANewcomersRecordIsCreatedWithTheSale(): void
    {
        $saved    = null;
        $invoices = $this->createMock(InvoiceRepositoryInterface::class);
        $invoices->method('save')->willReturnCallback(static function (Invoice $invoice) use (&$saved): void {
            $saved = $invoice;
        });

        $payers = $this->createMock(PayerDirectoryInterface::class);
        $payers->expects(self::once())->method('clientWithPhone')->with('+33618427731', self::CLINIC_ID)->willReturn(null);
        $payers->expects(self::never())->method('forClient');

        $newcomer = new CounterSaleNewClient(CounterSaleNewClient::MADAM, 'Benali', 'Nadia', '0618427731', 'nadia@example.fr');
        $registry = $this->createMock(CounterClientRegistryInterface::class);
        $registry->expects(self::once())->method('register')->with(self::CLINIC_ID, $newcomer)->willReturn(self::CLIENT);

        $this->saleHandler($invoices, $payers, registry: $registry)(new RecordCounterSale(self::CLINIC_ID, null, [['articleId' => self::ARTICLE, 'quantity' => 1]], 'CASH', newClient: $newcomer));

        self::assertInstanceOf(Invoice::class, $saved);
        self::assertSame(self::CLIENT, $saved->payer()->clientId()?->toString());
        self::assertSame('Nadia Benali', $saved->payer()->displayName());
        self::assertSame([], $saved->payer()->addressLines());
        self::assertSame(CounterSalePayer::NEW_CLIENT, $saved->counterSalePayer());
    }

    public function testANewcomerWithAnEmailOnlyIsNotLookedUpByPhone(): void
    {
        $payers = $this->createMock(PayerDirectoryInterface::class);
        $payers->expects(self::never())->method('clientWithPhone');

        $registry = $this->createMock(CounterClientRegistryInterface::class);
        $registry->expects(self::once())->method('register')->willReturn(self::CLIENT);

        $this->saleHandler($this->createStub(InvoiceRepositoryInterface::class), $payers, registry: $registry)(new RecordCounterSale(self::CLINIC_ID, null, [['articleId' => self::ARTICLE, 'quantity' => 1]], 'CASH', newClient: new CounterSaleNewClient(CounterSaleNewClient::MISTER, 'Petit', 'Lucas', email: 'lucas@example.fr')));
    }

    public function testANewcomerWhosePhoneIsKnownUsesTheExistingRecord(): void
    {
        $payers = $this->createMock(PayerDirectoryInterface::class);
        $payers->method('clientWithPhone')->willReturn(self::CLIENT);

        $registry = $this->createMock(CounterClientRegistryInterface::class);
        $registry->expects(self::never())->method('register');

        try {
            $this->saleHandler($this->createStub(InvoiceRepositoryInterface::class), $payers, registry: $registry)(new RecordCounterSale(self::CLINIC_ID, null, [['articleId' => self::ARTICLE, 'quantity' => 1]], 'CASH', newClient: new CounterSaleNewClient(CounterSaleNewClient::MADAM, 'Benali', 'Nadia', '0618427731')));
            self::fail('A known phone number must be refused.');
        } catch (CounterClientAlreadyKnownException $e) {
            self::assertSame(self::CLIENT, $e->clientId);
        }
    }

    public function testNoRecordIsCreatedForASaleThatCannotBeMade(): void
    {
        $catalog = $this->createMock(CounterCatalogInterface::class);
        $catalog->method('article')->willReturn(null);

        $registry = $this->createMock(CounterClientRegistryInterface::class);
        $registry->expects(self::never())->method('register');

        $this->expectException(InvalidInvoiceLineException::class);

        $this->saleHandler($this->createStub(InvoiceRepositoryInterface::class), $this->createStub(PayerDirectoryInterface::class), 2000, $catalog, $registry)(new RecordCounterSale(self::CLINIC_ID, null, [['articleId' => self::ARTICLE, 'quantity' => 1]], 'CASH', newClient: new CounterSaleNewClient(CounterSaleNewClient::MADAM, 'Benali', 'Nadia', '0618427731')));
    }

    /**
     * @return iterable<string, array{CounterSaleNewClient, string}>
     */
    public static function provideANewcomerNeedsEnoughToOpenTheirRecordCases(): iterable
    {
        yield 'unknown civility' => [new CounterSaleNewClient('dr', 'Benali', 'Nadia', '0618427731'), 'Unknown civility'];
        yield 'no first name' => [new CounterSaleNewClient('madam', 'Benali', ' ', '0618427731'), 'first and a last name'];
        yield 'name too long' => [new CounterSaleNewClient('madam', str_repeat('A', 41), 'Nadia', '0618427731'), 'first and a last name'];
        yield 'last name too short' => [new CounterSaleNewClient('madam', 'B', 'Nadia', '0618427731'), 'too short'];
        yield 'invalid phone' => [new CounterSaleNewClient('madam', 'Benali', 'Nadia', '0018427731'), 'Invalid phone'];
        yield 'e-mail without a domain' => [new CounterSaleNewClient('madam', 'Benali', 'Nadia', '', 'nadia@example'), 'Invalid e-mail'];
        yield 'e-mail not an address' => [new CounterSaleNewClient('madam', 'Benali', 'Nadia', '', 'nadia@@example.fr'), 'Invalid e-mail'];
        yield 'e-mail too long' => [new CounterSaleNewClient('madam', 'Benali', 'Nadia', '', str_repeat('a', 75) . '@ex.fr'), 'Invalid e-mail'];
        yield 'no contact' => [new CounterSaleNewClient('madam', 'Benali', 'Nadia'), 'mobile or an e-mail'];
    }

    #[DataProvider('provideANewcomerNeedsEnoughToOpenTheirRecordCases')]
    public function testANewcomerNeedsEnoughToOpenTheirRecord(CounterSaleNewClient $newcomer, string $message): void
    {
        $registry = $this->createMock(CounterClientRegistryInterface::class);
        $registry->expects(self::never())->method('register');

        $this->expectException(InvalidCounterClientException::class);
        $this->expectExceptionMessage($message);

        $this->saleHandler($this->createStub(InvoiceRepositoryInterface::class), $this->createStub(PayerDirectoryInterface::class), registry: $registry)(new RecordCounterSale(self::CLINIC_ID, null, [['articleId' => self::ARTICLE, 'quantity' => 1]], 'CASH', newClient: $newcomer));
    }

    /**
     * @return iterable<string, array{ClientTaxProfileInput, string}>
     */
    public static function provideAStructureNeedsAValidTaxProfileCases(): iterable
    {
        yield 'unknown status' => [new ClientTaxProfileInput('b2b', 'Élevage des Calanques', '523456788'), 'Unknown tax status'];
        yield 'no legal name' => [new ClientTaxProfileInput('individual', '  '), 'legal name'];
        yield 'legal name too long' => [new ClientTaxProfileInput('french_business', str_repeat('é', 256), '523456788'), 'legal name'];
        yield 'invalid SIREN' => [new ClientTaxProfileInput('french_business', 'Élevage des Calanques', '523456789'), 'SIREN'];
        yield 'no SIREN' => [new ClientTaxProfileInput('french_business', 'Élevage des Calanques'), 'SIREN'];
        yield 'VAT of another SIREN' => [new ClientTaxProfileInput('french_business', 'Élevage des Calanques', '523456788', 'FR19812345676'), 'VAT'];
    }

    #[DataProvider('provideAStructureNeedsAValidTaxProfileCases')]
    public function testAStructureNeedsAValidTaxProfile(ClientTaxProfileInput $profile, string $message): void
    {
        $registry = $this->createMock(CounterClientRegistryInterface::class);
        $registry->expects(self::never())->method('register');

        $this->expectException(InvalidCounterClientTaxProfileException::class);
        $this->expectExceptionMessageMatches('/' . preg_quote($message, '/') . '/i');

        $this->saleHandler($this->createStub(InvoiceRepositoryInterface::class), $this->createStub(PayerDirectoryInterface::class), registry: $registry)(new RecordCounterSale(self::CLINIC_ID, null, [['articleId' => self::ARTICLE, 'quantity' => 1]], 'CASH', newClient: new CounterSaleNewClient(CounterSaleNewClient::MADAM, 'Rostand', 'Claire', '0491000000', taxProfile: $profile)));
    }

    /**
     * @return iterable<string, array{ClientTaxProfileInput}>
     */
    public static function provideAValidStructureIsRegisteredCases(): iterable
    {
        yield 'VAT-liable with its VAT number' => [new ClientTaxProfileInput('french_business', 'Élevage des Calanques', '523 456 788 00012', 'FR31523456788')];
        yield 'not VAT-liable' => [new ClientTaxProfileInput('individual', 'Refuge des Collines')];
    }

    #[DataProvider('provideAValidStructureIsRegisteredCases')]
    public function testAValidStructureIsRegistered(ClientTaxProfileInput $profile): void
    {
        $payers = $this->createMock(PayerDirectoryInterface::class);
        $payers->method('forClient')->willReturn(new PayerSnapshotDto(self::CLIENT, (string) $profile->legalName, [], 'Mme Claire Rostand'));

        $registry = $this->createMock(CounterClientRegistryInterface::class);
        $registry->expects(self::once())->method('register')->willReturn(self::CLIENT);

        $this->saleHandler($this->createStub(InvoiceRepositoryInterface::class), $payers, registry: $registry)(new RecordCounterSale(self::CLINIC_ID, null, [['articleId' => self::ARTICLE, 'quantity' => 1]], 'CASH', newClient: new CounterSaleNewClient(CounterSaleNewClient::MADAM, 'Rostand', 'Claire', '0491000000', taxProfile: $profile)));
    }

    public function testANewcomerWithoutAPhoneHasNoInternationalNumber(): void
    {
        self::assertSame('', new CounterSaleNewClient('madam', 'Benali', 'Nadia')->internationalPhone());
        self::assertSame('+33618427731', new CounterSaleNewClient('madam', 'Benali', 'Nadia', '0618427731')->internationalPhone());
    }

    public function testAFreeArticleIsSettledWithoutAPayment(): void
    {
        $saved    = null;
        $invoices = $this->createMock(InvoiceRepositoryInterface::class);
        $invoices->method('save')->willReturnCallback(static function (Invoice $invoice) use (&$saved): void {
            $saved = $invoice;
        });

        $this->saleHandler($invoices, $this->createStub(PayerDirectoryInterface::class), 0)(new RecordCounterSale(self::CLINIC_ID, null, [['articleId' => self::ARTICLE, 'quantity' => 1]], 'CARD', contactDetailsRefused: true));

        self::assertInstanceOf(Invoice::class, $saved);
        self::assertSame([], $saved->payments());
        self::assertSame(InvoiceStatus::PAID, $saved->status());
    }

    public function testASaleNeedsAnArticle(): void
    {
        $this->expectException(InvalidInvoiceLineException::class);

        $this->saleHandler($this->createStub(InvoiceRepositoryInterface::class), $this->createStub(PayerDirectoryInterface::class))(new RecordCounterSale(self::CLINIC_ID, null, [], 'CARD'));
    }

    public function testAPrescriptionOnlyArticleIsNotSoldAtTheDesk(): void
    {
        $catalog = $this->createMock(CounterCatalogInterface::class);
        $catalog->method('article')->willReturn(null);

        $this->expectException(InvalidInvoiceLineException::class);
        $this->expectExceptionMessage('cannot be sold at the desk');

        $this->saleHandler($this->createStub(InvoiceRepositoryInterface::class), $this->createStub(PayerDirectoryInterface::class), 2000, $catalog)(new RecordCounterSale(self::CLINIC_ID, null, [['articleId' => self::ARTICLE, 'quantity' => 1]], 'CARD'));
    }

    public function testAQuantityIsBetweenOneAndAThousand(): void
    {
        $this->expectException(InvalidInvoiceLineException::class);
        $this->expectExceptionMessage('Invalid quantity');

        $this->saleHandler($this->createStub(InvoiceRepositoryInterface::class), $this->createStub(PayerDirectoryInterface::class))(new RecordCounterSale(self::CLINIC_ID, null, [['articleId' => self::ARTICLE, 'quantity' => 1000]], 'CARD'));
    }

    public function testAnUnknownPaymentMethodIsRefused(): void
    {
        $this->expectException(\ValueError::class);

        $this->saleHandler($this->createStub(InvoiceRepositoryInterface::class), $this->createStub(PayerDirectoryInterface::class))(new RecordCounterSale(self::CLINIC_ID, null, [['articleId' => self::ARTICLE, 'quantity' => 1]], 'BITCOIN'));
    }

    // ── Cheque remittance ──

    public function testEveryChequeInTheDrawerGoesToTheBank(): void
    {
        $desk = $this->createMock(CashDeskReadRepositoryInterface::class);
        $desk->method('pendingCheques')->willReturn([$this->cheque('01960000-0000-7000-8000-00000000f010', 4500), $this->cheque('01960000-0000-7000-8000-00000000f011', 3000)]);

        $saved    = null;
        $deposits = $this->createMock(ChequeDepositRepositoryInterface::class);
        $deposits->expects(self::once())->method('save')->willReturnCallback(static function (ChequeDeposit $deposit) use (&$saved): void {
            $saved = $deposit;
        });

        $id = (new DepositPendingChequesHandler($desk, $deposits, $this->uuids(), $this->clock()))(new DepositPendingCheques(self::CLINIC_ID, self::USER));

        self::assertInstanceOf(ChequeDeposit::class, $saved);
        self::assertSame($saved->id()->toString(), $id);
        self::assertSame(7500, $saved->total()->minorUnits());
        self::assertCount(2, $saved->paymentIds());
        self::assertSame(self::USER, $saved->depositedByUserId());
    }

    public function testAnEmptyDrawerHasNothingToDeposit(): void
    {
        $desk = $this->createMock(CashDeskReadRepositoryInterface::class);
        $desk->method('pendingCheques')->willReturn([]);

        $this->expectException(InvalidChequeDepositException::class);

        (new DepositPendingChequesHandler($desk, $this->createStub(ChequeDepositRepositoryInterface::class), $this->uuids(), $this->clock()))(new DepositPendingCheques(self::CLINIC_ID));
    }

    // ── Cash closing ──

    public function testClosingTheDayRecordsWhatTheTillShouldHoldLessTheRefunds(): void
    {
        $desk = $this->createMock(CashDeskReadRepositoryInterface::class);
        $desk->method('takings')->willReturn(new TakingsView(['CARD' => 15000, 'CASH' => 6000], ['CASH' => 1000, 'TRANSFER' => 500]));

        $saved    = null;
        $closings = $this->createMock(CashClosingRepositoryInterface::class);
        $closings->method('findForDate')->willReturn(null);
        $closings->method('nextNumber')->willReturn(7);
        $closings->expects(self::once())->method('save')->willReturnCallback(static function (CashClosing $closing) use (&$saved): void {
            $saved = $closing;
        });

        $number = (new CloseCashDeskHandler($desk, $closings, $this->uuids(), $this->clock()))(new CloseCashDesk(
            clinicId: self::CLINIC_ID,
            businessDate: '2026-10-14',
            dayStart: new \DateTimeImmutable('2026-10-13 22:00'),
            dayEnd: new \DateTimeImmutable('2026-10-14 21:59:59'),
            countedCashMinorUnits: 5000,
            countedChequesMinorUnits: null,
            comment: null,
            closedByUserId: self::USER,
        ));

        self::assertSame(7, $number);
        self::assertInstanceOf(CashClosing::class, $saved);
        self::assertSame(['CARD' => 15000, 'CASH' => 5000, 'TRANSFER' => -500], $saved->expectedMinorUnits());
        self::assertSame(0, $saved->gapMinorUnits());
    }

    public function testADayIsClosedOnce(): void
    {
        $closings = $this->createMock(CashClosingRepositoryInterface::class);
        $closings->method('findForDate')->willReturn(CashClosing::reconstitute(
            \App\Context\Billing\Domain\ValueObject\CashClosingId::fromString('01960000-0000-7000-8000-00000000f020'),
            ClinicId::fromString(self::CLINIC_ID),
            1,
            '2026-10-14',
            [],
            null,
            null,
            null,
            CurrencyCode::fromString('EUR'),
            $this->now(),
            null,
        ));
        $closings->expects(self::never())->method('save');

        $this->expectException(InvalidCashClosingException::class);
        $this->expectExceptionMessage('already closed');

        (new CloseCashDeskHandler($this->createStub(CashDeskReadRepositoryInterface::class), $closings, $this->uuids(), $this->clock()))(new CloseCashDesk(
            self::CLINIC_ID,
            '2026-10-14',
            new \DateTimeImmutable('2026-10-13 22:00'),
            new \DateTimeImmutable('2026-10-14 21:59:59'),
            null,
            null,
        ));
    }

    public function testAClosedDayIsNotAPendingCheque(): void
    {
        $view = new CashClosingView(3, '2026-10-14', $this->now());

        self::assertSame(3, $view->number);
        self::assertSame('2026-10-14', $view->businessDate);
    }

    public function testANewProfessionalIsBilledUnderItsLegalNameToItsContact(): void
    {
        $saved    = null;
        $invoices = $this->createMock(InvoiceRepositoryInterface::class);
        $invoices->method('save')->willReturnCallback(static function (Invoice $invoice) use (&$saved): void {
            $saved = $invoice;
        });

        $payers = $this->createMock(PayerDirectoryInterface::class);
        $payers->method('clientWithPhone')->willReturn(null);
        $payers->expects(self::once())->method('forClient')->with(self::CLIENT, self::CLINIC_ID)
            ->willReturn(new PayerSnapshotDto(self::CLIENT, 'Élevage des Calanques', ['13400 Aubagne'], 'Mme Claire Rostand'))
        ;

        $professional = new CounterSaleNewClient(CounterSaleNewClient::MADAM, 'Rostand', 'Claire', '0491000000', taxProfile: new ClientTaxProfileInput('french_business', 'Élevage des Calanques', '523456788'));
        $registry     = $this->createMock(CounterClientRegistryInterface::class);
        $registry->expects(self::once())->method('register')->with(self::CLINIC_ID, $professional)->willReturn(self::CLIENT);

        $this->saleHandler($invoices, $payers, registry: $registry)(new RecordCounterSale(self::CLINIC_ID, null, [['articleId' => self::ARTICLE, 'quantity' => 1]], 'CARD', newClient: $professional));

        self::assertInstanceOf(Invoice::class, $saved);
        self::assertSame('Élevage des Calanques', $saved->payer()->displayName());
        self::assertSame('Mme Claire Rostand', $saved->payer()->attention());
        self::assertSame(['13400 Aubagne'], $saved->payer()->addressLines());
        self::assertSame(CounterSalePayer::NEW_CLIENT, $saved->counterSalePayer());
    }

    public function testANewProfessionalNotReadBackFailsTheSale(): void
    {
        $invoices = $this->createMock(InvoiceRepositoryInterface::class);
        $invoices->expects(self::never())->method('save');

        $payers = $this->createMock(PayerDirectoryInterface::class);
        $payers->method('forClient')->willReturn(null);

        $registry = $this->createMock(CounterClientRegistryInterface::class);
        $registry->method('register')->willReturn(self::CLIENT);

        try {
            $this->saleHandler($invoices, $payers, registry: $registry)(new RecordCounterSale(self::CLINIC_ID, null, [['articleId' => self::ARTICLE, 'quantity' => 1]], 'CARD', newClient: new CounterSaleNewClient(CounterSaleNewClient::MISTER, 'Marchal', 'Hugo', email: 'hugo@lac.fr', taxProfile: new ClientTaxProfileInput('french_business', 'Centre équestre du Lac', '812345676'))));
            self::fail('The sale went out without the professional\'s legal name.');
        } catch (CounterClientNotReadBackException $e) {
            self::assertSame(self::CLIENT, $e->clientId);
        }
    }

    public function testAClientOnFileCarriesTheAttentionOfTheirSnapshot(): void
    {
        $saved    = null;
        $invoices = $this->createMock(InvoiceRepositoryInterface::class);
        $invoices->method('save')->willReturnCallback(static function (Invoice $invoice) use (&$saved): void {
            $saved = $invoice;
        });

        $payers = $this->createMock(PayerDirectoryInterface::class);
        $payers->method('forClient')->willReturn(new PayerSnapshotDto(self::CLIENT, 'Élevage des Calanques', [], 'Mme Claire Rostand'));

        $this->saleHandler($invoices, $payers)(new RecordCounterSale(self::CLINIC_ID, self::CLIENT, [['articleId' => self::ARTICLE, 'quantity' => 1]], 'CARD'));

        self::assertInstanceOf(Invoice::class, $saved);
        self::assertSame('Mme Claire Rostand', $saved->payer()->attention());
    }

    public function testAMobileIsToldApartFromALandline(): void
    {
        self::assertTrue(new CounterSaleNewClient(CounterSaleNewClient::MADAM, 'Benali', 'Nadia', '0718427731')->hasMobile());
        self::assertFalse(new CounterSaleNewClient(CounterSaleNewClient::MADAM, 'Benali', 'Nadia', '0491000000')->hasMobile());
        self::assertFalse(new CounterSaleNewClient(CounterSaleNewClient::MADAM, 'Benali', 'Nadia')->isProfessional());
    }

    // ── Helpers ──

    private function creditHandler(Invoice $invoice, CreditNoteRepositoryInterface $notes): IssueCreditNoteHandler
    {
        $invoices = $this->createMock(InvoiceRepositoryInterface::class);
        $invoices->method('get')->willReturn($invoice);

        $numbers = $this->createMock(InvoiceNumberGeneratorInterface::class);
        $numbers->method('nextCreditNote')->willReturn(InvoiceNumber::of(2026, 2, 'AV'));

        return new IssueCreditNoteHandler($invoices, $notes, $numbers, $this->uuids(), $this->clock());
    }

    /**
     * A credit note already issued against $invoice for one of its lines.
     */
    private function previousNote(Invoice $invoice, string $lineId, string $noteId): CreditNote
    {
        $line   = array_values(array_filter($invoice->lines(), static fn (InvoiceLine $l): bool => $l->id()->toString() === $lineId));
        $credit = new CreditNoteCalculator()->credit($invoice, $line, [], fn (): InvoiceLineId => InvoiceLineId::fromString($this->uuids()->generate()));

        return CreditNote::issue(
            CreditNoteId::fromString($noteId),
            ClinicId::fromString(self::CLINIC_ID),
            InvoiceNumber::of(2026, 1, 'AV'),
            InvoiceId::fromString(self::INVOICE_ID),
            'FAC-2026-000009',
            Payer::anonymous(),
            $credit['lines'],
            $credit['breakdown'],
            'Erreur',
            null,
            Money::fromMinorUnits(0, CurrencyCode::fromString('EUR')),
            CurrencyCode::fromString('EUR'),
            $this->now(),
        );
    }

    private function emptyNotes(): CreditNoteRepositoryInterface
    {
        $notes = $this->createMock(CreditNoteRepositoryInterface::class);
        $notes->method('forInvoice')->willReturn([]);

        return $notes;
    }

    private function saleHandler(
        InvoiceRepositoryInterface $invoices,
        PayerDirectoryInterface $payers,
        int $unitPrice = 2000,
        ?CounterCatalogInterface $catalog = null,
        ?CounterClientRegistryInterface $registry = null,
    ): RecordCounterSaleHandler {
        if (null === $catalog) {
            $catalog = $this->createMock(CounterCatalogInterface::class);
            $catalog->method('article')->willReturn(new CounterArticleDto(self::ARTICLE, 'Frontline Combo Chien pipettes', 'FRONTLINE-CB', $unitPrice, 'EUR', 'veterinary.drug.companion', 'excl'));
        }

        $tax = $this->createMock(TaxResolverInterface::class);
        $tax->method('resolve')->willReturn(new ResolvedTaxDto('20', OperationCategory::GOODS));

        $numbers = $this->createMock(InvoiceNumberGeneratorInterface::class);
        $numbers->method('next')->willReturn(InvoiceNumber::of(2026, 10));

        return new RecordCounterSaleHandler($invoices, $numbers, $catalog, new InvoiceLineFactory($tax, $this->uuids()), $payers, $registry ?? $this->createStub(CounterClientRegistryInterface::class), $this->uuids(), $this->clock());
    }

    private function cheque(string $paymentId, int $amount): ChequeView
    {
        return new ChequeView($paymentId, self::INVOICE_ID, 'FAC-2026-000009', 'Sophie Dupont', 'n° 1234567', $amount, 'EUR', $this->now());
    }

    private function draft(): Invoice
    {
        $invoice = Invoice::draft(
            InvoiceId::fromString(self::INVOICE_ID),
            ClinicId::fromString(self::CLINIC_ID),
            null,
            Payer::anonymous(),
            CurrencyCode::fromString('EUR'),
            $this->now(),
        );

        $invoice->addLine($this->line(self::LINE_A, 5000, 1000), $this->now());
        $invoice->addLine($this->line(self::LINE_B, 2000, 400), $this->now());

        return $invoice;
    }

    private function issued(): Invoice
    {
        $invoice = $this->draft();
        $invoice->issue(InvoiceNumber::of(2026, 9), $this->now());

        return $invoice;
    }

    private function line(string $id, int $net, int $tax, InvoiceLineSource $source = InvoiceLineSource::CONSULTATION, ?string $reference = null): InvoiceLine
    {
        $eur = CurrencyCode::fromString('EUR');

        return InvoiceLine::create(
            id: InvoiceLineId::fromString($id),
            label: 'Ligne ' . $net,
            code: null,
            quantity: '1',
            enteredUnitPrice: Money::fromMinorUnits($net + $tax, $eur),
            priceMode: PriceMode::INCLUDING_TAX,
            taxCategoryCode: 'veterinary.act.consultation',
            taxRatePercent: '20',
            operationCategory: OperationCategory::SERVICES,
            source: $source,
            sourceReference: $reference,
        );
    }

    private function payment(int $amount): Payment
    {
        return Payment::create(PaymentId::fromString('01960000-0000-7000-8000-00000000f0f1'), PaymentMethod::CARD, Money::fromMinorUnits($amount, CurrencyCode::fromString('EUR')), $this->now());
    }

    private function uuids(): UuidGeneratorInterface
    {
        $uuids = $this->createMock(UuidGeneratorInterface::class);
        $uuids->method('generate')->willReturnCallback(fn (): string => \sprintf('01960000-0000-7000-8000-%012d', ++$this->ids));

        return $uuids;
    }

    private function clock(): ClockInterface
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn($this->now());

        return $clock;
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-14 10:00:00');
    }
}
