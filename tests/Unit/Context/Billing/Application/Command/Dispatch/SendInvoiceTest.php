<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Application\Command\Dispatch;

use App\Context\Billing\Application\Command\SendInvoiceEmail\SendInvoiceEmail;
use App\Context\Billing\Application\Command\SendInvoiceEmail\SendInvoiceEmailHandler;
use App\Context\Billing\Application\Command\SendInvoiceToClient\InvoiceDispatchOutcome;
use App\Context\Billing\Application\Command\SendInvoiceToClient\SendInvoiceToClient;
use App\Context\Billing\Application\Command\SendInvoiceToClient\SendInvoiceToClientHandler;
use App\Context\Billing\Application\Dispatch\InvoiceMessages;
use App\Context\Billing\Application\Service\SentInvoiceFiles;
use App\Context\Billing\Domain\Dispatch\DispatchKind;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatch;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatchId;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Billing\Support\CapturingDispatchJobs;
use App\Tests\Unit\Context\Billing\Support\CapturingInvoiceNotifications;
use App\Tests\Unit\Context\Billing\Support\FakeClientContacts;
use App\Tests\Unit\Context\Billing\Support\FakeInvoicePdfRenderer;
use App\Tests\Unit\Context\Billing\Support\FakeInvoiceReads;
use App\Tests\Unit\Context\Billing\Support\FakeVisits;
use App\Tests\Unit\Context\Billing\Support\FixedClinicTimeZone;
use App\Tests\Unit\Context\Billing\Support\InMemoryDispatches;
use App\Tests\Unit\Context\Billing\Support\InMemorySentFiles;
use App\Tests\Unit\Context\Billing\Support\Invoices;
use App\Tests\Unit\System\Notification\Support\InMemoryFiles;
use PHPUnit\Framework\TestCase;

/**
 * « Envoyer »: the request validates, logs and queues; the worker renders
 * the PDF once and asks Notification for the e-mail.
 */
final class SendInvoiceTest extends TestCase
{
    private const string DRAFT     = '01960000-0000-7000-8000-00000000e201';
    private const string CANCELLED = '01960000-0000-7000-8000-00000000e202';
    private const string NO_MAIL   = '01960000-0000-7000-8000-00000000e203';
    private const string NO_PAYER  = '01960000-0000-7000-8000-00000000e204';
    private const string PAID      = '01960000-0000-7000-8000-00000000e205';
    private const string UNKNOWN   = '01960000-0000-7000-8000-00000000e206';
    private const string ARCHIVED  = '01960000-0000-7000-8000-00000000e207';
    private const string OTHER     = '01960000-0000-7000-8000-00000000e208';
    private const string GONE      = '01960000-0000-7000-8000-00000000e209';

    private FakeInvoiceReads $invoices;
    private FakeClientContacts $contacts;
    private InMemoryDispatches $dispatches;
    private CapturingDispatchJobs $jobs;
    private CapturingInvoiceNotifications $notifications;
    private FakeInvoicePdfRenderer $renderer;
    private InMemoryFiles $storage;
    private InMemorySentFiles $sentFiles;
    private int $sequence = 0;

    protected function setUp(): void
    {
        $this->invoices = new FakeInvoiceReads(
            Invoices::issued(),
            Invoices::issued(id: self::DRAFT, status: 'DRAFT', number: null),
            Invoices::issued(id: self::CANCELLED, status: 'CANCELLED'),
            Invoices::issued(id: self::NO_MAIL, client: self::NO_MAIL),
            Invoices::issued(id: self::NO_PAYER, client: null),
            Invoices::issued(id: self::PAID, status: 'PAID', due: 0),
            Invoices::issued(id: self::ARCHIVED, client: self::ARCHIVED),
        );
        $this->contacts = new FakeClientContacts(
            FakeClientContacts::contact(),
            FakeClientContacts::contact(clientId: self::NO_MAIL, email: null),
            FakeClientContacts::contact(clientId: self::ARCHIVED, archived: true),
        );
        $this->dispatches    = new InMemoryDispatches();
        $this->jobs          = new CapturingDispatchJobs();
        $this->notifications = new CapturingInvoiceNotifications();
        $this->renderer      = new FakeInvoicePdfRenderer('FAC-2026-000814.pdf');
        $this->storage       = new InMemoryFiles();
        $this->sentFiles     = new InMemorySentFiles();
    }

    public function testOnlyIssuedInvoicesOfPayersWithAnEmailAreQueued(): void
    {
        $outcome = $this->send([Invoices::INVOICE, self::DRAFT, self::CANCELLED, self::NO_MAIL, self::NO_PAYER, self::PAID, self::UNKNOWN, self::ARCHIVED, 'not-a-uuid', Invoices::INVOICE]);

        self::assertSame([Invoices::INVOICE, self::PAID], $outcome->queued);
        self::assertSame([
            self::DRAFT     => InvoiceDispatchOutcome::SKIP_DRAFT,
            self::CANCELLED => InvoiceDispatchOutcome::SKIP_NOT_ISSUED,
            self::UNKNOWN   => InvoiceDispatchOutcome::SKIP_UNKNOWN,
            'not-a-uuid'    => InvoiceDispatchOutcome::SKIP_UNKNOWN,
            self::NO_MAIL   => InvoiceDispatchOutcome::SKIP_NO_EMAIL,
            self::NO_PAYER  => InvoiceDispatchOutcome::SKIP_NO_EMAIL,
            self::ARCHIVED  => InvoiceDispatchOutcome::SKIP_NO_EMAIL,
        ], $outcome->skipped);
        self::assertSame([[Invoices::CLIENT, self::NO_MAIL, self::ARCHIVED]], $this->contacts->batches, 'One read for the payers.');

        self::assertCount(2, $this->dispatches->dispatches);
        $dispatch = $this->dispatches->dispatches[$this->jobs->emails[0]];
        self::assertSame([DispatchKind::SEND, ['EMAIL'], 'actor', Invoices::INVOICE], [$dispatch->kind(), $dispatch->channels(), $dispatch->authorUserId(), $dispatch->invoiceId()->toString()]);
        self::assertSame([], $this->renderer->rendered, 'AC 25: no PDF rendered in the request.');
    }

    public function testNothingToSendReadsNoContact(): void
    {
        self::assertSame([], $this->send([self::DRAFT])->queued);
        self::assertSame([], $this->contacts->batches);
    }

    public function testTheWorkerSendsTheEmailWithTheStoredPdfOnce(): void
    {
        $this->send([Invoices::INVOICE]);
        $dispatchId = $this->jobs->emails[0];

        $this->worker()(new SendInvoiceEmail($dispatchId));
        $this->worker()(new SendInvoiceEmail($dispatchId));

        self::assertCount(1, $this->notifications->messages, 'A replay sends nothing.');
        $message = $this->notifications->messages[0];
        self::assertSame(['INVOICE_SENT', 'la consultation de Minou du 16 octobre 2026', '88,70 €'], [$message->purpose, $message->parameters['purchaseLabel'], $message->parameters['balanceDue']]);
        self::assertSame(['billing/sent/invoice/' . Invoices::INVOICE . '.pdf', 'FAC-2026-000814.pdf'], [$message->attachment?->storageKey, $message->attachment?->filename]);
        self::assertSame(['message-1'], $this->dispatches->dispatches[$dispatchId]->notificationIds());

        // Sent again later: same file, not rendered again (AC 25b).
        $this->send([Invoices::INVOICE]);
        $this->worker()(new SendInvoiceEmail($this->jobs->emails[1]));

        self::assertSame([Invoices::INVOICE], $this->renderer->rendered);
        self::assertSame($message->attachment?->storageKey, $this->notifications->messages[1]->attachment?->storageKey);
    }

    public function testACounterSaleIsSentWithoutVisit(): void
    {
        $this->invoices->invoices[self::OTHER] = Invoices::issued(id: self::OTHER, admission: null);
        $this->send([self::OTHER]);

        $this->worker()(new SendInvoiceEmail($this->jobs->emails[0]));

        self::assertSame('vos achats du 16/10/2026', $this->notifications->messages[0]->parameters['purchaseLabel']);
    }

    public function testNothingIsSentForAnUnknownDispatchOrWhenThingsChanged(): void
    {
        $this->worker()(new SendInvoiceEmail(self::GONE));

        $this->log(self::CANCELLED);
        $this->log(self::NO_MAIL);
        $this->log(self::NO_PAYER);

        foreach (array_keys($this->dispatches->dispatches) as $id) {
            $this->worker()(new SendInvoiceEmail($id));
        }

        self::assertSame([], $this->notifications->messages);
        self::assertSame([], $this->renderer->rendered);
        self::assertSame(
            [InvoiceDispatchOutcome::SKIP_NOT_ISSUED, InvoiceDispatchOutcome::SKIP_NO_EMAIL, InvoiceDispatchOutcome::SKIP_NO_EMAIL],
            array_values(array_map(static fn (InvoiceDispatch $d): ?string => $d->skippedReason(), $this->dispatches->dispatches)),
        );
    }

    /**
     * @param list<string> $ids
     */
    private function send(array $ids): InvoiceDispatchOutcome
    {
        $handler = new SendInvoiceToClientHandler($this->invoices, $this->contacts, $this->dispatches, $this->jobs, $this->uuids(), $this->clock());

        return $handler(new SendInvoiceToClient(Invoices::CLINIC, $ids, 'actor'));
    }

    private function worker(): SendInvoiceEmailHandler
    {
        return new SendInvoiceEmailHandler(
            $this->dispatches,
            $this->invoices,
            $this->contacts,
            new FakeVisits(),
            new SentInvoiceFiles($this->sentFiles, $this->renderer, $this->storage, $this->clock()),
            new InvoiceMessages(new FixedClinicTimeZone()),
            $this->notifications,
        );
    }

    /** A dispatch logged before the invoice or its payer changed. */
    private function log(string $invoiceId): void
    {
        $this->dispatches->save(InvoiceDispatch::log(
            InvoiceDispatchId::fromString($this->uuids()->generate()),
            ClinicId::fromString(Invoices::CLINIC),
            InvoiceId::fromString($invoiceId),
            DispatchKind::SEND,
            ['EMAIL'],
            null,
            new \DateTimeImmutable(),
        ));
    }

    private function uuids(): UuidGeneratorInterface
    {
        return new class($this->sequence) implements UuidGeneratorInterface {
            public function __construct(private int &$sequence)
            {
            }

            public function generate(): string
            {
                return \sprintf('01960000-0000-7000-8000-0000000ef%03d', ++$this->sequence);
            }
        };
    }

    private function clock(): FrozenClock
    {
        return new FrozenClock(new \DateTimeImmutable('2026-10-26 10:00:00', new \DateTimeZone('UTC')));
    }
}
