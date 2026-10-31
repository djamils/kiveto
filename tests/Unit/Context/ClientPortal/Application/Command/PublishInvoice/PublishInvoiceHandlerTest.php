<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Command\PublishInvoice;

use App\Context\ClientPortal\Application\Command\PublishInvoice\PublishInvoice;
use App\Context\ClientPortal\Application\Command\PublishInvoice\PublishInvoiceHandler;
use App\Context\ClientPortal\Application\Port\InvoicePublication;
use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Context\ClientPortal\Domain\Document\PublicationStatus;
use App\Tests\Unit\Context\ClientPortal\Support\DocumentWorld as W;
use PHPUnit\Framework\TestCase;

final class PublishInvoiceHandlerTest extends TestCase
{
    private const string INVOICE   = '01960000-0000-7000-8000-0000000a0001';
    private const string ADMISSION = '01960000-0000-7000-8000-0000000a1001';
    private const string CONSULT   = '01960000-0000-7000-8000-0000000a2001';

    public function testAnInvoiceThatIsNotPublishableIsSkipped(): void
    {
        $world = new W();

        $this->handler($world)(new PublishInvoice(W::CLINIC, self::INVOICE));

        self::assertSame([], $world->documents->documents);
        self::assertSame([], $world->fakes->events);
    }

    public function testALinkedPayerGetsTheInvoiceWithTheVisitAnimalAndItsOnlyConsultation(): void
    {
        $world = new W();
        $world->link();
        $this->invoice($world, self::ADMISSION);
        $world->sources->patients[self::ADMISSION]      = W::PATIENT;
        $world->sources->animals[W::PATIENT]            = W::ANIMAL;
        $world->sources->consultations[self::ADMISSION] = [self::CONSULT];

        $this->handler($world)(new PublishInvoice(W::CLINIC, self::INVOICE));

        $document = $world->documents->only();
        self::assertSame(
            [
                W::CLINIC, W::CLIENT, DocumentType::INVOICE, self::INVOICE, self::CONSULT, W::PATIENT, [W::ANIMAL],
                'Facture F-2026-0042', '2026-10-20 10:00', null, PublicationStatus::PENDING_FILE, 1,
                '2026-10-29 09:00',
            ],
            [
                $document->clinicId(), $document->clientId(), $document->type(), $document->sourceRef(),
                $document->consultationId(), $document->patientId(), $document->petIds(), $document->title(),
                $document->issuedAt()->format('Y-m-d H:i'), $document->practitionerName(), $document->status(),
                $document->pendingFileVersion(), $document->publishedAt()->format('Y-m-d H:i'),
            ],
        );
        self::assertSame(['store:' . $document->id()->toString() . ':1'], $world->sources->jobs);
        self::assertSame(['client_portal.document.published.v1'], $world->fakes->eventNames());
    }

    public function testAVisitWithSeveralConsultationsKeepsNone(): void
    {
        $world = new W();
        $this->invoice($world, self::ADMISSION);
        $world->sources->patients[self::ADMISSION]      = W::PATIENT;
        $world->sources->consultations[self::ADMISSION] = [self::CONSULT, '01960000-0000-7000-8000-0000000a2002'];

        $this->handler($world)(new PublishInvoice(W::CLINIC, self::INVOICE));

        $document = $world->documents->only();
        self::assertSame(
            [null, W::PATIENT, [], PublicationStatus::AWAITING_LINK, null],
            [
                $document->consultationId(), $document->patientId(), $document->petIds(), $document->status(),
                $document->pendingFileVersion(),
            ],
            'the patient is not reconciled to an animal yet; no link: no file',
        );
        self::assertSame([], $world->sources->jobs);
    }

    public function testAVisitWithoutPatientOrAnInvoiceWithoutVisitHasNoPet(): void
    {
        $world = new W();
        $this->invoice($world, self::ADMISSION);
        $this->handler($world)(new PublishInvoice(W::CLINIC, self::INVOICE));
        $document = $world->documents->only();
        self::assertSame([null, null, []], [$document->consultationId(), $document->patientId(), $document->petIds()]);

        $world = new W();
        $this->invoice($world, null);
        $world->sources->consultations[self::ADMISSION] = [self::CONSULT];
        $this->handler($world)(new PublishInvoice(W::CLINIC, self::INVOICE));
        $document = $world->documents->only();
        self::assertSame([null, null, []], [$document->consultationId(), $document->patientId(), $document->petIds()]);
    }

    public function testAWithdrawalTombstoneWins(): void
    {
        $world = new W();
        $world->link();
        $this->invoice($world, null);
        $world->document(DocumentType::INVOICE, PublicationStatus::WITHDRAWN, 0, sourceRef: self::INVOICE);

        $this->handler($world)(new PublishInvoice(W::CLINIC, self::INVOICE));

        self::assertSame(PublicationStatus::WITHDRAWN, $world->documents->only()->status());
        self::assertSame([], $world->sources->jobs);
        self::assertSame([], $world->fakes->events);
    }

    private function invoice(W $world, ?string $admissionId): void
    {
        $world->invoices->publications[self::INVOICE] = new InvoicePublication(
            self::INVOICE,
            W::CLIENT,
            'F-2026-0042',
            new \DateTimeImmutable('2026-10-20 10:00', new \DateTimeZone('UTC')),
            $admissionId,
        );
    }

    private function handler(W $world): PublishInvoiceHandler
    {
        return new PublishInvoiceHandler(
            $world->invoices,
            $world->sources,
            $world->sources,
            $world->publisher(),
            $world->fakes,
            $world->clock,
        );
    }
}
