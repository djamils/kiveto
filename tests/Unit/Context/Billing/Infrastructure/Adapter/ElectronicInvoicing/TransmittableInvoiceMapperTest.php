<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Infrastructure\Adapter\ElectronicInvoicing;

use App\Context\Billing\Domain\ValueObject\SellerIdentity;
use App\Context\Billing\Infrastructure\Adapter\ElectronicInvoicing\CommandBusElectronicInvoiceTransmitter;
use App\Context\Billing\Infrastructure\Adapter\ElectronicInvoicing\TransmittableInvoiceMapper;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\RequestInvoiceTransmission;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableInvoice;
use App\System\ElectronicInvoicing\Application\Query\GetTransmissionStatus\GetTransmissionStatus;
use App\System\ElectronicInvoicing\Application\Query\GetTransmissionStatus\TransmissionStatusView;
use App\System\ElectronicInvoicing\Infrastructure\Format\Cii\CiiInvoiceRenderer;
use App\System\ElectronicInvoicing\Infrastructure\Format\XsdValidator;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Support\Billing\ProfessionalDocuments;
use App\Tests\Support\Billing\SellerIdentities;
use App\Tests\Unit\System\ElectronicInvoicing\Infrastructure\Format\AfnorSamplesXsdTest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TransmittableInvoiceMapperTest extends TestCase
{
    /**
     * AC 34: lines are named as on the PDF; nothing of the visit leaves.
     */
    public function testAnAdmissionInvoiceKeepsOnlyWhatItsPdfPrints(): void
    {
        $document = new TransmittableInvoiceMapper()->fromInvoice(ProfessionalDocuments::issuedInvoice(), SellerIdentities::complete());

        self::assertSame(ProfessionalDocuments::INVOICE, $document->documentId);
        self::assertSame(TransmittableInvoice::KIND_INVOICE, $document->kind);
        self::assertSame('FAC-2026-000007', $document->number);
        self::assertSame('380', $document->typeCode);
        self::assertSame('M1', $document->invoicingFramework);
        self::assertSame('B2B', $document->processingRule);
        self::assertSame(['Vaccin CHPPiL', 'Croquettes rénales'], array_map(static fn ($line): string => $line->name, $document->lines));
        self::assertSame(['1', '2'], array_map(static fn ($line): string => $line->lineId, $document->lines));
        self::assertSame('Mme Claire Rostand', $document->buyerContact);
        self::assertSame('523456788', $document->buyer->siren);
        self::assertSame('0225', $document->buyer->electronicAddress->scheme);
        self::assertSame('812456903', $document->seller->siren);
        self::assertSame(['REG', 'PMD', 'PMT', 'AAB'], array_map(static fn ($note): string => $note->subjectCode, $document->notes));
        self::assertNotNull($document->dueDate);
        self::assertSame($document->issueDate, $document->deliveryDate);

        // Frozen amounts: totals are consistent with the breakdown.
        self::assertSame($document->lineTotal - $document->allowanceTotal, $document->taxBasis);
        self::assertSame($document->taxBasis + $document->vatTotal, $document->grossTotal);
        self::assertSame($document->grossTotal - $document->paidAmount, $document->duePayable);
        // BT-113: what was paid at the desk when it was issued.
        self::assertSame(1000, $document->paidAmount);
        self::assertSame($document->taxBasis, array_sum(array_map(static fn ($vat): int => $vat->base, $document->vatBreakdown)));
        self::assertNotEmpty($document->allowances);

        $serialised = (string) json_encode($document->toArray(), \JSON_UNESCAPED_UNICODE);
        self::assertStringNotContainsString('Médor', $serialised);
        self::assertStringNotContainsString('rappel', $serialised);
        self::assertStringNotContainsString('inquiet', $serialised);

        // The neutral document renders into a valid CII file.
        self::assertValidCii($document);
    }

    public function testACreditNoteRefersToItsInvoice(): void
    {
        $invoice = ProfessionalDocuments::issuedInvoice();
        $note    = new TransmittableInvoiceMapper()->fromCreditNote(ProfessionalDocuments::creditNoteOf($invoice), SellerIdentities::complete());

        self::assertSame(TransmittableInvoice::KIND_CREDIT_NOTE, $note->kind);
        self::assertSame(ProfessionalDocuments::CREDIT_NOTE, $note->documentId);
        self::assertSame('381', $note->typeCode);
        self::assertSame('FAC-2026-000007', $note->precedingInvoiceNumber);
        self::assertSame('2026-10-21', $note->precedingInvoiceDate?->format('Y-m-d'));
        self::assertSame(['Vaccin CHPPiL'], array_map(static fn ($line): string => $line->name, $note->lines));
        self::assertSame(0, $note->paidAmount);
        self::assertSame($note->grossTotal, $note->duePayable);
        self::assertNull($note->dueDate);
        self::assertValidCii($note);
    }

    /**
     * AC 17c: a professional sale paid at the desk leaves framed « already
     * paid » (S2, B2 or M2) with BT-113 equal to its total incl. VAT.
     */
    public function testASalePaidAtTheDeskLeavesPaidWithItsWholeTotal(): void
    {
        $document = new TransmittableInvoiceMapper()->fromInvoice(ProfessionalDocuments::issuedInvoice(paidInFull: true), SellerIdentities::complete());

        self::assertSame('M2', $document->invoicingFramework);
        self::assertSame($document->grossTotal, $document->paidAmount);
        self::assertSame(0, $document->duePayable);
        self::assertNull($document->dueDate);
        self::assertValidCii($document);
    }

    /**
     * F12: the issue date is the local date in Europe/Paris, as on the PDF —
     * 23:30 stays that day, 00:30 is the next one.
     *
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideTheIssueDateIsTheLocalDateInParisCases(): iterable
    {
        yield '23:30 in Paris (21:30 UTC)' => ['2026-10-21 21:30:00', '2026-10-21', '20261021'];
        yield '00:30 in Paris (22:30 UTC the day before)' => ['2026-10-21 22:30:00', '2026-10-22', '20261022'];
        yield '00:30 in Paris in winter (23:30 UTC)' => ['2026-12-31 23:30:00', '2027-01-01', '20270101'];
    }

    #[DataProvider('provideTheIssueDateIsTheLocalDateInParisCases')]
    public function testTheIssueDateIsTheLocalDateInParis(string $utc, string $date, string $cii): void
    {
        $invoice = ProfessionalDocuments::issuedInvoice(new \DateTimeImmutable($utc, new \DateTimeZone('UTC')));
        $mapper  = new TransmittableInvoiceMapper();

        $document = TransmittableInvoice::fromArray($mapper->fromInvoice($invoice, SellerIdentities::complete())->toArray());
        $note     = $mapper->fromCreditNote(ProfessionalDocuments::creditNoteOf($invoice), SellerIdentities::complete());

        self::assertSame($date, $document->issueDate->format('Y-m-d'));
        self::assertSame($date, $document->deliveryDate?->format('Y-m-d'));
        self::assertSame($date, $note->precedingInvoiceDate?->format('Y-m-d'));
        self::assertStringContainsString(\sprintf('<udt:DateTimeString format="102">%s</udt:DateTimeString>', $cii), self::cii($document));
    }

    public function testASellerWithoutLegalSentenceSendsOnlyTheProfessionalMentions(): void
    {
        $complete = SellerIdentities::complete();
        $seller   = new SellerIdentity(
            $complete->legalName,
            $complete->siren,
            $complete->vatNumber,
            $complete->streetLines,
            $complete->postalCode,
            $complete->city,
            $complete->countryCode,
            '  ',
            $complete->professionalPaymentTermsDays,
            $complete->iban,
            $complete->bic,
            true,
        );

        $document = new TransmittableInvoiceMapper()->fromInvoice(ProfessionalDocuments::issuedInvoice(), $seller);

        self::assertSame(['PMD', 'PMT', 'AAB'], array_map(static fn ($note): string => $note->subjectCode, $document->notes));
    }

    public function testADraftIsNeverTransmitted(): void
    {
        $this->expectException(\LogicException::class);

        new TransmittableInvoiceMapper()->fromInvoice(ProfessionalDocuments::professionalDraft(), SellerIdentities::complete());
    }

    public function testAPrivateClientsInvoiceIsNeverTransmitted(): void
    {
        $this->expectException(\LogicException::class);

        new TransmittableInvoiceMapper()->fromInvoice(ProfessionalDocuments::privateInvoice(), SellerIdentities::complete());
    }

    public function testTheTransmitterFreezesThenSendsTheDocumentAsFrozen(): void
    {
        $invoice  = ProfessionalDocuments::issuedInvoice();
        $commands = [];
        $bus      = $this->createMock(CommandBusInterface::class);
        $bus->expects(self::exactly(2))->method('dispatch')->willReturnCallback(static function (object $command) use (&$commands): null {
            $commands[] = $command;

            return null;
        });

        $transmitter = new CommandBusElectronicInvoiceTransmitter($bus, $this->createStub(QueryBusInterface::class), new TransmittableInvoiceMapper(), new FrozenClock(ProfessionalDocuments::now()));
        $invoiceDoc  = $transmitter->freezeInvoice($invoice, SellerIdentities::complete());
        $noteDoc     = $transmitter->freezeCreditNote(ProfessionalDocuments::creditNoteOf($invoice), SellerIdentities::complete());
        $transmitter->transmit(ProfessionalDocuments::CLINIC, $invoiceDoc);
        $transmitter->transmit(ProfessionalDocuments::CLINIC, $noteDoc);

        self::assertContainsOnlyInstancesOf(RequestInvoiceTransmission::class, $commands);
        self::assertSame(
            [[ProfessionalDocuments::CLINIC, TransmittableInvoice::KIND_INVOICE], [ProfessionalDocuments::CLINIC, TransmittableInvoice::KIND_CREDIT_NOTE]],
            array_map(static fn (RequestInvoiceTransmission $c): array => [$c->clinicId, $c->invoice->kind], $commands),
        );
        self::assertSame($invoiceDoc, $commands[0]->invoice->toArray());
    }

    public function testTheTransmitterReadsWhatElectronicInvoicingHas(): void
    {
        $queries = $this->createStub(QueryBusInterface::class);
        $queries->method('ask')->willReturnCallback(static fn (GetTransmissionStatus $query): ?TransmissionStatusView => 'known' === $query->documentId
            ? new TransmissionStatusView(ProfessionalDocuments::CLINIC, 'known', 4, 'not_required', null, null, null, '213', 'The credited invoice was rejected (213).', false, null)
            : null);

        $transmitter = new CommandBusElectronicInvoiceTransmitter($this->createStub(CommandBusInterface::class), $queries, new TransmittableInvoiceMapper(), new FrozenClock(ProfessionalDocuments::now()));
        $status      = $transmitter->status('known');

        self::assertNotNull($status);
        self::assertSame(['known', 4, 'not_required', '213', false], [$status->documentId, $status->sequence, $status->state, $status->reasonCode, $status->requiresAction]);
        self::assertSame(ProfessionalDocuments::now()->format('c'), $status->updatedAt->format('c'));
        self::assertNull($transmitter->status('unknown'));
    }

    private static function assertValidCii(TransmittableInvoice $document): void
    {
        self::assertSame([], new XsdValidator()->validate(self::cii($document), AfnorSamplesXsdTest::SCHEMA));
    }

    private static function cii(TransmittableInvoice $document): string
    {
        return new CiiInvoiceRenderer(new XsdValidator(), AfnorSamplesXsdTest::SCHEMA)->render($document, $document->buyer->electronicAddress);
    }
}
