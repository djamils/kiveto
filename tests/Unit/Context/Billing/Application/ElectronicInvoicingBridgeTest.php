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
use App\Context\Billing\Application\Port\SellerIdentityProviderInterface;
use App\Context\Billing\Application\Port\TransmissionStatusProjectionInterface;
use App\Context\Billing\Application\Port\TransmissionStatusUpdate;
use App\Context\Billing\Domain\Event\CreditNoteIssued;
use App\Context\Billing\Domain\Event\InvoiceIssued;
use App\Context\Billing\Domain\Event\ProfessionalDocumentIssuedIntegrationEvent;
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
use App\Tests\Support\Billing\RecordingTransmitter;
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

    /**
     * F3: the document is frozen at issue — the seller and the amount paid
     * as they are then — and kept to be sent as is.
     */
    public function testAProfessionalInvoiceIsFrozenMarkedRequestedAndAnnounced(): void
    {
        $projection = new InMemoryTransmissionStatusProjection();

        $this->invoiceBridge($projection)(
            new InvoiceIssued(self::DOC, self::CLINIC, 'FAC-2026-000001', 12000, 'EUR', '2026-10-21T10:00:00+02:00', 'B2B'),
        );

        self::assertSame([[self::DOC, TransmissionStatusProjectionInterface::KIND_INVOICE]], $projection->requested);
        self::assertSame(
            ['documentId' => ProfessionalDocuments::INVOICE, 'number' => 'FAC-2026-000007', 'paid' => 1000, 'seller' => SellerIdentities::complete()->legalName],
            $projection->documents[self::DOC],
        );
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

        $this->invoiceBridge($projection)(
            new InvoiceIssued(self::DOC, self::CLINIC, 'FAC-2026-000001', 12000, 'EUR', '2026-10-21T10:00:00+02:00'),
        );
        $this->creditNoteBridge($projection)(
            new CreditNoteIssued(self::DOC, self::CLINIC, self::DOC, 'AV-2026-000001', 1000, 0, 'EUR', '2026-10-21T10:00:00+02:00'),
        );

        self::assertSame([], $projection->requested);
        self::assertSame([], $this->published);
    }

    public function testAProfessionalCreditNoteFollowsItsInvoice(): void
    {
        $projection = new InMemoryTransmissionStatusProjection();

        $this->creditNoteBridge($projection)(
            new CreditNoteIssued(self::DOC, self::CLINIC, ProfessionalDocuments::INVOICE, 'AV-2026-000001', 1000, 0, 'EUR', '2026-10-21T10:00:00+02:00', 'B2B'),
        );

        self::assertSame([[self::DOC, TransmissionStatusProjectionInterface::KIND_CREDIT_NOTE]], $projection->requested);
        self::assertSame('AV-2026-000001', $projection->documents[self::DOC]['number'] ?? null);
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

    /**
     * F3: what is sent is the document frozen at issue — nothing is read again.
     */
    public function testTheDocumentFrozenAtIssueIsHandedOver(): void
    {
        $projection                       = new InMemoryTransmissionStatusProjection();
        $projection->documents[self::DOC] = ['documentId' => self::DOC, 'paid' => 1000];
        $transmitter                      = new RecordingTransmitter();

        new RequestDocumentTransmissionHandler($projection, $transmitter)(new RequestDocumentTransmission(self::CLINIC, self::DOC, 'invoice'));
        // Nothing frozen: not a professional document, nothing to send.
        new RequestDocumentTransmissionHandler($projection, $transmitter)(new RequestDocumentTransmission(self::CLINIC, 'private', 'invoice'));

        self::assertSame([[self::CLINIC, ['documentId' => self::DOC, 'paid' => 1000]]], $transmitter->transmitted);
    }

    // ── Reconciliation ──

    /**
     * AC 33: re-requested after an hour, given up after a day — F8: unless
     * electronic invoicing has the document already, whose status is then
     * projected; one failure is logged and the others go on.
     */
    public function testTheReconciliationRetriesThenGivesUp(): void
    {
        $projection          = new InMemoryTransmissionStatusProjection();
        $projection->waiting = [
            ['clinicId' => self::CLINIC, 'documentId' => 'failing', 'kind' => 'invoice', 'issuedAt' => new \DateTimeImmutable('2026-10-20 08:00:00')],
            ['clinicId' => self::CLINIC, 'documentId' => 'recent', 'kind' => 'invoice', 'issuedAt' => new \DateTimeImmutable('2026-10-21 08:00:00')],
            ['clinicId' => self::CLINIC, 'documentId' => 'known', 'kind' => 'invoice', 'issuedAt' => new \DateTimeImmutable('2026-10-20 08:00:00')],
            ['clinicId' => self::CLINIC, 'documentId' => 'stale', 'kind' => 'credit_note', 'issuedAt' => new \DateTimeImmutable('2026-10-20 09:00:00')],
        ];
        $transmitter                    = new RecordingTransmitter();
        $transmitter->failing           = ['failing'];
        $transmitter->statuses['known'] = new TransmissionStatusUpdate('known', 3, 'queued', null, null, null, null, null, false, new \DateTimeImmutable());
        $errors                         = [];
        $logger                         = $this->createMock(LoggerInterface::class);
        $logger->method('error')->willReturnCallback(static function (string $message, array $context) use (&$errors): void {
            $errors[] = [$message, $context['documentId'] ?? null, isset($context['exception'])];
        });

        new ReconcileProfessionalTransmissionsHandler($projection, $transmitter, $this->commands(), $this->clock(), $logger)(new ReconcileProfessionalTransmissions());

        self::assertSame('2026-10-21 09:00:00', $projection->askedBefore?->format('Y-m-d H:i:s'));
        self::assertSame([[self::CLINIC, 'recent', 'invoice']], $this->requests());
        self::assertSame([['stale', 'credit_note']], $projection->failed);
        self::assertSame(['known'], array_map(static fn (TransmissionStatusUpdate $u): string => $u->documentId, $projection->applied));
        self::assertSame([
            ['A professional document could not be reconciled with electronic invoicing.', 'failing', true],
            ['A professional document was never handed to electronic invoicing.', 'stale', false],
        ], $errors);
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

    private function invoiceBridge(InMemoryTransmissionStatusProjection $projection): InvoiceIssuedIntegrationBridge
    {
        $invoices = $this->createMock(InvoiceRepositoryInterface::class);
        $invoices->method('get')->willReturn(ProfessionalDocuments::issuedInvoice());

        return new InvoiceIssuedIntegrationBridge($this->publisher(), $projection, $invoices, $this->sellers(), new RecordingTransmitter(), $this->clock());
    }

    private function creditNoteBridge(InMemoryTransmissionStatusProjection $projection): CreditNoteIssuedIntegrationBridge
    {
        $creditNotes = $this->createMock(CreditNoteRepositoryInterface::class);
        $creditNotes->method('get')->willReturn(ProfessionalDocuments::creditNoteOf(ProfessionalDocuments::issuedInvoice()));

        return new CreditNoteIssuedIntegrationBridge($this->publisher(), $projection, $creditNotes, $this->sellers(), new RecordingTransmitter(), $this->clock());
    }

    private function sellers(): SellerIdentityProviderInterface
    {
        $sellers = $this->createMock(SellerIdentityProviderInterface::class);
        $sellers->method('forClinic')->willReturn(SellerIdentities::complete());

        return $sellers;
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
