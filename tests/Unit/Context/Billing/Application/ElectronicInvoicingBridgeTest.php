<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Application;

use App\Context\Billing\Application\Command\ReconcileProfessionalTransmissions\ReconcileProfessionalTransmissions;
use App\Context\Billing\Application\Command\ReconcileProfessionalTransmissions\ReconcileProfessionalTransmissionsHandler;
use App\Context\Billing\Application\Command\RecordTransmissionStatus\RecordTransmissionStatus;
use App\Context\Billing\Application\Command\RecordTransmissionStatus\RecordTransmissionStatusHandler;
use App\Context\Billing\Application\Command\RequestDocumentTransmission\RequestDocumentTransmission;
use App\Context\Billing\Application\Command\RequestDocumentTransmission\RequestDocumentTransmissionHandler;
use App\Context\Billing\Application\EventSubscriber\OnInvoiceTransmissionStatusChanged;
use App\Context\Billing\Application\EventSubscriber\OnProfessionalDocumentIssuedRequestTransmission;
use App\Context\Billing\Application\Port\ElectronicInvoiceTransmitterInterface;
use App\Context\Billing\Application\Port\SellerIdentityProviderInterface;
use App\Context\Billing\Application\Port\TransmissionStatusProjectionInterface;
use App\Context\Billing\Application\Port\TransmissionStatusUpdate;
use App\Context\Billing\Domain\CreditNote;
use App\Context\Billing\Domain\Event\CreditNoteIssued;
use App\Context\Billing\Domain\Event\InvoiceIssued;
use App\Context\Billing\Domain\Event\ProfessionalDocumentIssuedIntegrationEvent;
use App\Context\Billing\Domain\Invoice;
use App\Context\Billing\Domain\Repository\CreditNoteRepositoryInterface;
use App\Context\Billing\Domain\Repository\InvoiceRepositoryInterface;
use App\Context\Billing\Infrastructure\Messaging\Consumer\CreditNoteIssuedIntegrationBridge;
use App\Context\Billing\Infrastructure\Messaging\Consumer\InvoiceIssuedIntegrationBridge;
use App\Context\Billing\Infrastructure\Scheduler\BillingScheduleProvider;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\IntegrationEventBusInterface;
use App\Shared\Application\Event\IntegrationEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\System\ElectronicInvoicing\Domain\Event\InvoiceTransmissionStatusChangedIntegrationEvent;
use App\Tests\Support\Billing\InMemoryTransmissionStatusProjection;
use App\Tests\Support\Billing\ProfessionalDocuments;
use App\Tests\Support\Billing\SellerIdentities;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

#[AllowMockObjectsWithoutExpectations]
final class ElectronicInvoicingBridgeTest extends TestCase
{
    private const string CLINIC = ProfessionalDocuments::CLINIC;
    private const string DOC    = '01960000-0000-7000-8000-0000000fe001';

    /** @var list<object> */
    private array $dispatched = [];

    /** @var list<object> */
    private array $published = [];

    // ── Issued → requested (outbox) ──

    public function testAProfessionalInvoiceIsMarkedRequestedAndAnnounced(): void
    {
        $projection = new InMemoryTransmissionStatusProjection();

        new InvoiceIssuedIntegrationBridge($this->publisher(), $projection, $this->clock())(
            new InvoiceIssued(self::DOC, self::CLINIC, 'FAC-2026-000001', 12000, 'EUR', '2026-10-21T10:00:00+02:00', 'B2B'),
        );

        self::assertSame([[self::DOC, TransmissionStatusProjectionInterface::KIND_INVOICE]], $projection->requested);
        self::assertCount(1, $this->published);
        $event = $this->published[0];
        self::assertInstanceOf(ProfessionalDocumentIssuedIntegrationEvent::class, $event);
        self::assertSame([
            'clinicId'   => self::CLINIC,
            'documentId' => self::DOC,
            'kind'       => 'invoice',
            'number'     => 'FAC-2026-000001',
            'issuedAt'   => '2026-10-21T10:00:00+02:00',
        ], $event->payload());
        self::assertSame(self::DOC, $event->aggregateId());
    }

    /**
     * AC 39: a private client's invoice requests nothing.
     */
    public function testAPrivateInvoiceGoesNowhere(): void
    {
        $projection = new InMemoryTransmissionStatusProjection();

        new InvoiceIssuedIntegrationBridge($this->publisher(), $projection, $this->clock())(
            new InvoiceIssued(self::DOC, self::CLINIC, 'FAC-2026-000001', 12000, 'EUR', '2026-10-21T10:00:00+02:00'),
        );
        new CreditNoteIssuedIntegrationBridge($this->publisher(), $projection, $this->clock())(
            new CreditNoteIssued(self::DOC, self::CLINIC, self::DOC, 'AV-2026-000001', 1000, 0, 'EUR', '2026-10-21T10:00:00+02:00'),
        );

        self::assertSame([], $projection->requested);
        self::assertSame([], $this->published);
    }

    public function testAProfessionalCreditNoteFollowsItsInvoice(): void
    {
        $projection = new InMemoryTransmissionStatusProjection();

        new CreditNoteIssuedIntegrationBridge($this->publisher(), $projection, $this->clock())(
            new CreditNoteIssued(self::DOC, self::CLINIC, ProfessionalDocuments::INVOICE, 'AV-2026-000001', 1000, 0, 'EUR', '2026-10-21T10:00:00+02:00', 'B2B'),
        );

        self::assertSame([[self::DOC, TransmissionStatusProjectionInterface::KIND_CREDIT_NOTE]], $projection->requested);
        $event = $this->published[0] ?? null;
        self::assertInstanceOf(ProfessionalDocumentIssuedIntegrationEvent::class, $event);
        self::assertSame('credit_note', $event->kind);
    }

    public function testTheAnnouncementRequestsTheTransmission(): void
    {
        new OnProfessionalDocumentIssuedRequestTransmission($this->commands())(
            new ProfessionalDocumentIssuedIntegrationEvent(self::CLINIC, self::DOC, 'invoice', 'FAC-2026-000001', '2026-10-21T10:00:00+02:00'),
        );

        self::assertSame([[self::CLINIC, self::DOC, 'invoice']], $this->requests());
    }

    // ── Request ──

    public function testAnIssuedProfessionalInvoiceIsHandedOver(): void
    {
        $invoice     = ProfessionalDocuments::issuedInvoice();
        $transmitter = $this->createMock(ElectronicInvoiceTransmitterInterface::class);
        $transmitter->expects(self::once())->method('transmitInvoice')->with($invoice, self::anything());
        $transmitter->expects(self::never())->method('transmitCreditNote');

        $this->requestHandler($transmitter, invoice: $invoice)(new RequestDocumentTransmission(self::CLINIC, ProfessionalDocuments::INVOICE, 'invoice'));
    }

    public function testNeitherAPrivateInvoiceNorADraftIsHandedOver(): void
    {
        $transmitter = $this->createMock(ElectronicInvoiceTransmitterInterface::class);
        $transmitter->expects(self::never())->method('transmitInvoice');

        $this->requestHandler($transmitter, invoice: ProfessionalDocuments::privateInvoice())(new RequestDocumentTransmission(self::CLINIC, ProfessionalDocuments::INVOICE, 'invoice'));
        $this->requestHandler($transmitter, invoice: ProfessionalDocuments::professionalDraft())(new RequestDocumentTransmission(self::CLINIC, ProfessionalDocuments::INVOICE, 'invoice'));
    }

    public function testAProfessionalCreditNoteIsHandedOverAndAPrivateOneIsNot(): void
    {
        $professional = ProfessionalDocuments::creditNoteOf(ProfessionalDocuments::issuedInvoice());
        $transmitter  = $this->createMock(ElectronicInvoiceTransmitterInterface::class);
        $transmitter->expects(self::once())->method('transmitCreditNote')->with($professional, self::anything());

        $this->requestHandler($transmitter, creditNote: $professional)(new RequestDocumentTransmission(self::CLINIC, ProfessionalDocuments::CREDIT_NOTE, 'credit_note'));
        $this->requestHandler($transmitter, creditNote: ProfessionalDocuments::creditNoteOf(ProfessionalDocuments::privateInvoice()))(new RequestDocumentTransmission(self::CLINIC, ProfessionalDocuments::CREDIT_NOTE, 'credit_note'));
    }

    // ── Reconciliation ──

    /**
     * AC 33: re-requested after an hour, given up after a day.
     */
    public function testTheReconciliationRetriesThenGivesUp(): void
    {
        $projection          = new InMemoryTransmissionStatusProjection();
        $projection->waiting = [
            ['clinicId' => self::CLINIC, 'documentId' => 'recent', 'kind' => 'invoice', 'issuedAt' => new \DateTimeImmutable('2026-10-21 08:00:00')],
            ['clinicId' => self::CLINIC, 'documentId' => 'stale', 'kind' => 'credit_note', 'issuedAt' => new \DateTimeImmutable('2026-10-20 09:00:00')],
        ];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(self::anything(), ['documentId' => 'stale', 'kind' => 'credit_note']);

        new ReconcileProfessionalTransmissionsHandler($projection, $this->commands(), $this->clock(), $logger)(new ReconcileProfessionalTransmissions());

        self::assertSame('2026-10-21 09:00:00', $projection->askedBefore?->format('Y-m-d H:i:s'));
        self::assertSame([[self::CLINIC, 'recent', 'invoice']], $this->requests());
        self::assertSame([['stale', 'credit_note']], $projection->failed);
    }

    // ── Status reports ──

    public function testAStatusReportIsRecordedOnTheDocument(): void
    {
        new OnInvoiceTransmissionStatusChanged($this->commands())(
            new InvoiceTransmissionStatusChangedIntegrationEvent(self::CLINIC, self::DOC, 7, 'accepted', null, 205, 'Approuvée', null, null, false, '2026-10-21T10:00:00+02:00'),
        );

        $command = $this->dispatched[0] ?? null;
        self::assertInstanceOf(RecordTransmissionStatus::class, $command);

        $projection = new InMemoryTransmissionStatusProjection();
        self::assertTrue(new RecordTransmissionStatusHandler($projection)($command));

        $update = $projection->applied[0] ?? null;
        self::assertInstanceOf(TransmissionStatusUpdate::class, $update);
        self::assertSame([self::DOC, 7, 'accepted', 205, 'Approuvée', false], [$update->documentId, $update->sequence, $update->state, $update->statusCode, $update->statusLabel, $update->requiresAction]);
        self::assertSame('2026-10-21 10:00:00', $update->updatedAt->format('Y-m-d H:i:s'));
    }

    // ── Schedule ──

    public function testTheReconciliationRunsHourlyAtMinuteTwentyThree(): void
    {
        $schedule = new BillingScheduleProvider(new LockFactory(new InMemoryStore()))->getSchedule();
        $messages = $schedule->getRecurringMessages();

        self::assertCount(1, $messages);
        self::assertNotNull($schedule->getLock());
        $message = $messages[0];
        $trigger = $message->getTrigger();
        self::assertInstanceOf(TriggerInterface::class, $trigger);
        $next = $trigger->getNextRunDate(new \DateTimeImmutable('2026-10-21 10:40:00', new \DateTimeZone('Europe/Paris')));
        self::assertSame('11:23', $next?->setTimezone(new \DateTimeZone('Europe/Paris'))->format('H:i'));

        $context = new MessageContext('billing', $message->getId(), $trigger, new \DateTimeImmutable());
        self::assertInstanceOf(ReconcileProfessionalTransmissions::class, iterator_to_array($message->getMessages($context))[0] ?? null);
    }

    private function requestHandler(ElectronicInvoiceTransmitterInterface $transmitter, ?Invoice $invoice = null, ?CreditNote $creditNote = null): RequestDocumentTransmissionHandler
    {
        $invoices = $this->createMock(InvoiceRepositoryInterface::class);
        if (null !== $invoice) {
            $invoices->method('get')->willReturn($invoice);
        }

        $creditNotes = $this->createMock(CreditNoteRepositoryInterface::class);
        if (null !== $creditNote) {
            $creditNotes->method('get')->willReturn($creditNote);
        }

        $sellers = $this->createMock(SellerIdentityProviderInterface::class);
        $sellers->method('forClinic')->willReturn(SellerIdentities::complete());

        return new RequestDocumentTransmissionHandler($invoices, $creditNotes, $sellers, $transmitter);
    }

    /**
     * @return list<array{string, string, string}>
     */
    private function requests(): array
    {
        return array_map(
            static fn (object $command): array => $command instanceof RequestDocumentTransmission ? [$command->clinicId, $command->documentId, $command->kind] : [$command::class, '', ''],
            $this->dispatched,
        );
    }

    private function publisher(): IntegrationEventPublisher
    {
        $bus = $this->createMock(IntegrationEventBusInterface::class);
        $bus->method('publish')->willReturnCallback(function (array $stamps, object ...$events): void {
            $this->published = [...$this->published, ...array_values($events)];
        });

        return new IntegrationEventPublisher($bus);
    }

    private function commands(): CommandBusInterface
    {
        $bus = $this->createMock(CommandBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $command): null {
            $this->dispatched[] = $command;

            return null;
        });

        return $bus;
    }

    private function clock(): ClockInterface
    {
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-10-21 10:00:00'));

        return $clock;
    }
}
