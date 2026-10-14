<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Application\Query;

use App\Context\Billing\Application\Port\CashDeskReadRepositoryInterface;
use App\Context\Billing\Application\Port\CounterArticleDto;
use App\Context\Billing\Application\Port\CounterCatalogInterface;
use App\Context\Billing\Application\Port\InvoiceReadRepositoryInterface;
use App\Context\Billing\Application\Port\ResolvedTaxDto;
use App\Context\Billing\Application\Port\TaxResolverInterface;
use App\Context\Billing\Application\Query\GetBillingStats\BillingStats;
use App\Context\Billing\Application\Query\GetBillingStats\GetBillingStats;
use App\Context\Billing\Application\Query\GetBillingStats\GetBillingStatsHandler;
use App\Context\Billing\Application\Query\GetCashDesk\CashClosingView;
use App\Context\Billing\Application\Query\GetCashDesk\ChequeView;
use App\Context\Billing\Application\Query\GetCashDesk\GetCashDesk;
use App\Context\Billing\Application\Query\GetCashDesk\GetCashDeskHandler;
use App\Context\Billing\Application\Query\GetCashDesk\TakingsView;
use App\Context\Billing\Application\Query\GetChequeDeposit\ChequeDepositView;
use App\Context\Billing\Application\Query\GetChequeDeposit\GetChequeDeposit;
use App\Context\Billing\Application\Query\GetChequeDeposit\GetChequeDepositHandler;
use App\Context\Billing\Application\Query\GetCreditNoteDetails\CreditNoteDetails;
use App\Context\Billing\Application\Query\GetCreditNoteDetails\GetCreditNoteDetails;
use App\Context\Billing\Application\Query\GetCreditNoteDetails\GetCreditNoteDetailsHandler;
use App\Context\Billing\Application\Query\SearchCounterArticles\CounterArticleView;
use App\Context\Billing\Application\Query\SearchCounterArticles\SearchCounterArticles;
use App\Context\Billing\Application\Query\SearchCounterArticles\SearchCounterArticlesHandler;
use App\Context\Billing\Application\Query\SearchInvoices\SearchInvoicesCriteria;
use App\Context\Billing\Domain\ValueObject\ChequeDepositId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\CreditNoteId;
use PHPUnit\Framework\TestCase;

final class DeskQueryHandlersTest extends TestCase
{
    private const string CLINIC_ID = '01960000-0000-7000-8000-00000000a701';
    private const string DOC_ID    = '01960000-0000-7000-8000-00000000a702';

    public function testTheTillOfADayGathersItsTakingsChequesAndClosing(): void
    {
        $takings = new TakingsView(['CARD' => 1000, 'CASH' => 500], []);
        $cheque  = new ChequeView(self::DOC_ID, self::DOC_ID, 'FAC-2026-000001', 'Sophie Dupont', null, 4500, 'EUR', new \DateTimeImmutable());
        $closing = new CashClosingView(2, '2026-10-14', new \DateTimeImmutable());

        $desk = $this->createMock(CashDeskReadRepositoryInterface::class);
        $desk->expects(self::once())->method('takings')->willReturn($takings);
        $desk->expects(self::once())->method('pendingCheques')->willReturn([$cheque, $cheque]);
        $desk->expects(self::once())->method('closingFor')->with(self::anything(), '2026-10-14')->willReturn($closing);
        $desk->expects(self::once())->method('closingCount')->willReturn(5);

        $view = (new GetCashDeskHandler($desk))(new GetCashDesk(self::CLINIC_ID, '2026-10-14', new \DateTimeImmutable(), new \DateTimeImmutable()));

        self::assertSame($takings, $view->takings);
        self::assertSame(1500, $view->takings->totalCollected());
        self::assertSame(9000, $view->pendingChequesTotal());
        self::assertSame($closing, $view->closing);
        self::assertSame(5, $view->closingCount);
    }

    public function testARemittanceIsReadInTheClinicScope(): void
    {
        $deposit = new ChequeDepositView(self::DOC_ID, new \DateTimeImmutable(), 4500, 'EUR', []);

        $desk = $this->createMock(CashDeskReadRepositoryInterface::class);
        $desk->expects(self::once())->method('chequeDeposit')
            ->with(
                self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()),
                self::callback(static fn (ChequeDepositId $id): bool => self::DOC_ID === $id->toString()),
            )
            ->willReturn($deposit)
        ;

        self::assertSame($deposit, (new GetChequeDepositHandler($desk))(new GetChequeDeposit(self::CLINIC_ID, self::DOC_ID)));
    }

    public function testAnotherClinicsRemittanceIsNotFound(): void
    {
        $desk = $this->createStub(CashDeskReadRepositoryInterface::class);
        $desk->method('chequeDeposit')->willReturn(null);

        $this->expectException(\DomainException::class);

        (new GetChequeDepositHandler($desk))(new GetChequeDeposit(self::CLINIC_ID, self::DOC_ID));
    }

    public function testACreditNoteIsReadInTheClinicScope(): void
    {
        $note = new CreditNoteDetails(
            id: self::DOC_ID,
            clinicId: self::CLINIC_ID,
            number: 'AV-2026-000001',
            invoiceId: self::DOC_ID,
            invoiceNumber: 'FAC-2026-000001',
            invoiceIssuedAt: null,
            admissionId: null,
            payerClientId: null,
            payerName: 'Client de passage',
            payerAddressLines: [],
            lines: [],
            taxBreakdown: [],
            currency: 'EUR',
            totalNetMinorUnits: 1000,
            totalTaxMinorUnits: 200,
            totalGrossMinorUnits: 1200,
            reason: 'Retour produit',
            refundMethod: null,
            refundMethodLabel: null,
            refundedMinorUnits: 0,
            issuedAt: new \DateTimeImmutable(),
            issuedByUserId: null,
        );

        $repository = $this->createMock(InvoiceReadRepositoryInterface::class);
        $repository->expects(self::once())->method('creditNoteDetails')
            ->with(
                self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()),
                self::callback(static fn (CreditNoteId $id): bool => self::DOC_ID === $id->toString()),
            )
            ->willReturn($note)
        ;

        self::assertSame($note, (new GetCreditNoteDetailsHandler($repository))(new GetCreditNoteDetails(self::CLINIC_ID, self::DOC_ID)));
    }

    public function testCounterArticlesComeWithTheirPriceAllTaxesIncluded(): void
    {
        $catalog = $this->createMock(CounterCatalogInterface::class);
        $catalog->expects(self::once())->method('search')->with(self::CLINIC_ID, 'front', 50)
            ->willReturn([new CounterArticleDto(self::DOC_ID, 'Frontline Combo Chien pipettes', 'FRONTLINE-CB', 1890, 'EUR', 'veterinary.drug.companion')])
        ;

        $tax = $this->createMock(TaxResolverInterface::class);
        $tax->expects(self::once())->method('resolve')->with(1890, 'EUR', 'veterinary.drug.companion', self::CLINIC_ID)
            ->willReturn(new ResolvedTaxDto(1890, 104, 1994, '5.5'))
        ;

        $articles = (new SearchCounterArticlesHandler($catalog, $tax))(new SearchCounterArticles(self::CLINIC_ID, '  front ', 500));

        self::assertCount(1, $articles);
        self::assertInstanceOf(CounterArticleView::class, $articles[0]);
        self::assertSame(1994, $articles[0]->unitGrossMinorUnits);
        self::assertSame(1890, $articles[0]->unitNetMinorUnits);
        self::assertSame('5.5', $articles[0]->taxRatePercent);
        self::assertSame('FRONTLINE-CB', $articles[0]->code);
    }

    public function testTheOverdueLimitIsPassedThrough(): void
    {
        $stats  = new BillingStats(100, 200, 1, 1, 'EUR', 1, 200);
        $before = new \DateTimeImmutable('2026-09-14');

        $repository = $this->createMock(InvoiceReadRepositoryInterface::class);
        $repository->expects(self::once())->method('stats')->with(self::anything(), self::anything(), self::anything(), $before)->willReturn($stats);

        self::assertSame($stats, (new GetBillingStatsHandler($repository))(new GetBillingStats(self::CLINIC_ID, new \DateTimeImmutable(), new \DateTimeImmutable(), $before)));
    }

    public function testCriteriaSayWhichDocumentsTheyList(): void
    {
        $default = new SearchInvoicesCriteria();
        $both    = new SearchInvoicesCriteria(kinds: [SearchInvoicesCriteria::KIND_INVOICE, SearchInvoicesCriteria::KIND_CREDIT_NOTE]);

        self::assertTrue($default->lists(SearchInvoicesCriteria::KIND_INVOICE));
        self::assertFalse($default->lists(SearchInvoicesCriteria::KIND_CREDIT_NOTE));
        self::assertTrue($both->lists(SearchInvoicesCriteria::KIND_CREDIT_NOTE));
        self::assertFalse($default->isImpossible());
        self::assertTrue(new SearchInvoicesCriteria(kinds: [])->isImpossible());
        self::assertTrue(new SearchInvoicesCriteria(admissionIds: [])->isImpossible());
        self::assertFalse(new SearchInvoicesCriteria(admissionIds: [self::DOC_ID])->isImpossible());
    }
}
