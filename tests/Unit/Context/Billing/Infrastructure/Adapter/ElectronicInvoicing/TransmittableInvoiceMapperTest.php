<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Infrastructure\Adapter\ElectronicInvoicing;

use App\Context\Billing\Domain\ValueObject\SellerIdentity;
use App\Context\Billing\Infrastructure\Adapter\ElectronicInvoicing\CommandBusElectronicInvoiceTransmitter;
use App\Context\Billing\Infrastructure\Adapter\ElectronicInvoicing\TransmittableInvoiceMapper;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\RequestInvoiceTransmission;
use App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission\TransmittableInvoice;
use App\System\ElectronicInvoicing\Infrastructure\Format\Cii\CiiInvoiceRenderer;
use App\System\ElectronicInvoicing\Infrastructure\Format\XsdValidator;
use App\Tests\Support\Billing\ProfessionalDocuments;
use App\Tests\Support\Billing\SellerIdentities;
use App\Tests\Unit\System\ElectronicInvoicing\Infrastructure\Format\AfnorSamplesXsdTest;
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
        self::assertSame(0, $document->paidAmount);
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
        self::assertSame($invoice->issuedAt(), $note->precedingInvoiceDate);
        self::assertSame(['Vaccin CHPPiL'], array_map(static fn ($line): string => $line->name, $note->lines));
        self::assertSame(0, $note->paidAmount);
        self::assertSame($note->grossTotal, $note->duePayable);
        self::assertNull($note->dueDate);
        self::assertValidCii($note);
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

    public function testTheTransmitterRequestsTheTransmissionOnTheCommandBus(): void
    {
        $invoice  = ProfessionalDocuments::issuedInvoice();
        $commands = [];
        $bus      = $this->createMock(CommandBusInterface::class);
        $bus->expects(self::exactly(2))->method('dispatch')->willReturnCallback(static function (object $command) use (&$commands): null {
            $commands[] = $command;

            return null;
        });

        $transmitter = new CommandBusElectronicInvoiceTransmitter($bus, new TransmittableInvoiceMapper());
        $transmitter->transmitInvoice($invoice, SellerIdentities::complete());
        $transmitter->transmitCreditNote(ProfessionalDocuments::creditNoteOf($invoice), SellerIdentities::complete());

        self::assertContainsOnlyInstancesOf(RequestInvoiceTransmission::class, $commands);
        self::assertSame(
            [[ProfessionalDocuments::CLINIC, TransmittableInvoice::KIND_INVOICE], [ProfessionalDocuments::CLINIC, TransmittableInvoice::KIND_CREDIT_NOTE]],
            array_map(static fn (RequestInvoiceTransmission $c): array => [$c->clinicId, $c->invoice->kind], $commands),
        );
    }

    private static function assertValidCii(TransmittableInvoice $document): void
    {
        $xml = new CiiInvoiceRenderer(new XsdValidator(), AfnorSamplesXsdTest::SCHEMA)->render($document, $document->buyer->electronicAddress);

        self::assertSame([], new XsdValidator()->validate($xml, AfnorSamplesXsdTest::SCHEMA));
    }
}
