<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Application\Command\Dispatch;

use App\Context\Billing\Application\Command\RemindUnpaidInvoices\RemindUnpaidInvoices;
use App\Context\Billing\Application\Command\RemindUnpaidInvoices\RemindUnpaidInvoicesHandler;
use App\Context\Billing\Application\Command\SendInvoiceToClient\InvoiceDispatchOutcome;
use App\Context\Billing\Application\Command\SendUnpaidReminder\SendUnpaidReminder;
use App\Context\Billing\Application\Command\SendUnpaidReminder\SendUnpaidReminderHandler;
use App\Context\Billing\Application\Dispatch\InvoiceMessages;
use App\Context\Billing\Application\Dispatch\UnpaidReminderSelection;
use App\Context\Billing\Application\Query\PrepareUnpaidReminder\PrepareUnpaidReminder;
use App\Context\Billing\Application\Query\PrepareUnpaidReminder\PrepareUnpaidReminderHandler;
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
use App\Tests\Unit\Context\Billing\Support\CountingSegments;
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
 * « Relancer »: the confirmation counts, the request re-checks, logs and
 * queues, the worker sends on the « Facturation » channels.
 */
final class RemindUnpaidTest extends TestCase
{
    // Payers: SMS + e-mail consent / SMS consent only / no consent (transactional e-mail) / unreachable / archived.
    private const string BOTH        = '01960000-0000-7000-8000-00000000e501';
    private const string SMS_ONLY    = '01960000-0000-7000-8000-00000000e502';
    private const string MAIL        = '01960000-0000-7000-8000-00000000e503';
    private const string UNREACHABLE = '01960000-0000-7000-8000-00000000e504';
    private const string ARCHIVED    = '01960000-0000-7000-8000-00000000e505';

    // Invoices.
    private const string I_BOTH      = '01960000-0000-7000-8000-00000000e511';
    private const string I_SMS       = '01960000-0000-7000-8000-00000000e512';
    private const string I_SMS_2     = '01960000-0000-7000-8000-00000000e513';
    private const string I_MAIL      = '01960000-0000-7000-8000-00000000e514';
    private const string I_TODAY     = '01960000-0000-7000-8000-00000000e515';
    private const string I_YESTERDAY = '01960000-0000-7000-8000-00000000e516';
    private const string I_PAID      = '01960000-0000-7000-8000-00000000e517';
    private const string I_ZERO      = '01960000-0000-7000-8000-00000000e518';
    private const string I_DRAFT     = '01960000-0000-7000-8000-00000000e519';
    private const string I_NO_PAYER  = '01960000-0000-7000-8000-00000000e520';
    private const string I_UNREACH   = '01960000-0000-7000-8000-00000000e521';
    private const string I_ARCHIVED  = '01960000-0000-7000-8000-00000000e522';
    private const string I_UNKNOWN   = '01960000-0000-7000-8000-00000000e523';
    private const string I_UNSTORED  = '01960000-0000-7000-8000-00000000e524';

    private FakeInvoiceReads $invoices;
    private FakeClientContacts $contacts;
    private InMemoryDispatches $dispatches;
    private CapturingDispatchJobs $jobs;
    private CapturingInvoiceNotifications $notifications;
    private CountingSegments $segments;
    private FakeInvoicePdfRenderer $renderer;
    private InMemorySentFiles $sentFiles;
    private int $sequence = 0;

    protected function setUp(): void
    {
        $this->invoices = new FakeInvoiceReads(
            Invoices::issued(id: self::I_BOTH, client: self::BOTH, due: 6450),
            Invoices::issued(id: self::I_SMS, client: self::SMS_ONLY, status: 'PARTIALLY_PAID', due: 2450),
            Invoices::issued(id: self::I_SMS_2, client: self::SMS_ONLY, due: 1250),
            Invoices::issued(id: self::I_MAIL, client: self::MAIL),
            Invoices::issued(id: self::I_TODAY, client: self::MAIL),
            Invoices::issued(id: self::I_YESTERDAY, client: self::MAIL),
            Invoices::issued(id: self::I_PAID, client: self::MAIL, status: 'PAID', due: 0),
            Invoices::issued(id: self::I_ZERO, client: self::MAIL, due: 0),
            Invoices::issued(id: self::I_DRAFT, client: self::MAIL, status: 'DRAFT', number: null),
            Invoices::issued(id: self::I_NO_PAYER, client: null),
            Invoices::issued(id: self::I_UNREACH, client: self::UNREACHABLE),
            Invoices::issued(id: self::I_ARCHIVED, client: self::ARCHIVED),
            Invoices::issued(id: self::I_UNSTORED, client: self::MAIL, admission: null),
        );
        $this->contacts = new FakeClientContacts(
            FakeClientContacts::contact(clientId: self::BOTH, billing: ['EMAIL', 'SMS']),
            FakeClientContacts::contact(clientId: self::SMS_ONLY, billing: ['SMS']),
            FakeClientContacts::contact(clientId: self::MAIL, mobile: null),
            FakeClientContacts::contact(clientId: self::UNREACHABLE, email: null, mobile: null),
            FakeClientContacts::contact(clientId: self::ARCHIVED, archived: true),
        );
        $this->dispatches = new InMemoryDispatches();
        // Paris: 26/10 00:30 is today, 25/10 23:30 was yesterday.
        $this->reminded(self::I_TODAY, '2026-10-25 23:30:00');
        $this->reminded(self::I_YESTERDAY, '2026-10-25 22:30:00');

        $this->jobs          = new CapturingDispatchJobs();
        $this->notifications = new CapturingInvoiceNotifications();
        $this->segments      = new CountingSegments();
        $this->renderer      = new FakeInvoicePdfRenderer();
        $this->sentFiles     = new InMemorySentFiles();
    }

    public function testTheConfirmationCountsClientsAndSmsSegments(): void
    {
        $preview = $this->prepare(null);

        self::assertSame([self::I_BOTH, self::I_SMS, self::I_SMS_2, self::I_MAIL, self::I_YESTERDAY, self::I_UNSTORED], $preview->invoiceIds);
        self::assertSame(3, $preview->clientCount);
        self::assertSame(3, $preview->smsCount);
        // « 64,50 € », « 24,50 € » and « 12,50 € » are as long: one estimate for three SMS.
        self::assertCount(1, $this->segments->asked);
        self::assertSame(['invoiceNumber' => 'FAC-2026-000814', 'shortDate' => '16/10', 'balanceDue' => '64,50 €'], $this->segments->asked[0]);
        self::assertSame(3, $preview->smsSegments);
        self::assertSame(4, $preview->skippedCount);
    }

    public function testASelectionSaysWhyInvoicesAreLeftOut(): void
    {
        $outcome = $this->remind([self::I_BOTH, self::I_TODAY, self::I_PAID, self::I_ZERO, self::I_DRAFT, self::I_NO_PAYER, self::I_UNREACH, self::I_ARCHIVED, self::I_UNKNOWN, 'nope', self::I_BOTH]);

        self::assertSame([self::I_BOTH], $outcome->queued);
        self::assertSame([
            'nope'           => InvoiceDispatchOutcome::SKIP_UNKNOWN,
            self::I_PAID     => InvoiceDispatchOutcome::SKIP_NOTHING_DUE,
            self::I_ZERO     => InvoiceDispatchOutcome::SKIP_NOTHING_DUE,
            self::I_DRAFT    => InvoiceDispatchOutcome::SKIP_NOTHING_DUE,
            self::I_UNKNOWN  => InvoiceDispatchOutcome::SKIP_NOTHING_DUE,
            self::I_TODAY    => InvoiceDispatchOutcome::SKIP_REMINDED_TODAY,
            self::I_NO_PAYER => InvoiceDispatchOutcome::SKIP_NO_CONTACT,
            self::I_UNREACH  => InvoiceDispatchOutcome::SKIP_NO_CONTACT,
            self::I_ARCHIVED => InvoiceDispatchOutcome::SKIP_NO_CONTACT,
        ], $outcome->skipped);

        self::assertSame(
            [[self::I_BOTH, self::I_TODAY, self::I_PAID, self::I_ZERO, self::I_DRAFT, self::I_NO_PAYER, self::I_UNREACH, self::I_ARCHIVED, self::I_UNKNOWN]],
            $this->dispatches->locked,
            'The invoices are locked before the same-day check; malformed ids are not.',
        );

        $dispatch = $this->dispatches->dispatches[$this->jobs->reminders[0]];
        self::assertSame([DispatchKind::REMINDER, ['EMAIL', 'SMS'], 'actor'], [$dispatch->kind(), $dispatch->channels(), $dispatch->authorUserId()]);
        self::assertSame(1, $this->dispatches->countReminders(ClinicId::fromString(Invoices::CLINIC), InvoiceId::fromString(self::I_BOTH)));
        self::assertSame([], $this->prepare([])->invoiceIds);
        self::assertSame([], $this->renderer->rendered, 'No PDF inside the request.');
    }

    public function testAnInvoiceIsRemindedOnceADay(): void
    {
        self::assertSame([self::I_BOTH], $this->remind([self::I_BOTH])->queued);
        self::assertSame([self::I_BOTH => InvoiceDispatchOutcome::SKIP_REMINDED_TODAY], $this->remind([self::I_BOTH])->skipped);
    }

    public function testTheReminderGoesByEmailWithThePdfAndBySmsWithoutFallback(): void
    {
        $this->remind([self::I_BOTH]);
        $this->worker()(new SendUnpaidReminder($this->jobs->reminders[0]));
        $this->worker()(new SendUnpaidReminder($this->jobs->reminders[0]));

        [$email, $sms] = $this->notifications->messages;
        self::assertCount(2, $this->notifications->messages, 'A replay sends nothing.');
        self::assertSame(['EMAIL', 'des soins de Minou', 'billing/sent/invoice/' . self::I_BOTH . '.pdf'], [$email->channel, $email->parameters['careLabel'], $email->attachment?->storageKey]);
        self::assertSame(['SMS', null], [$sms->channel, $sms->fallbackAddress]);
        self::assertSame(['message-1', 'message-2'], $this->dispatches->dispatches[$this->jobs->reminders[0]]->notificationIds());
    }

    public function testAnSmsReminderFallsBackOnEmailAndRendersNoPdf(): void
    {
        $this->remind([self::I_SMS]);
        $this->worker()(new SendUnpaidReminder($this->jobs->reminders[0]));

        self::assertCount(1, $this->notifications->messages);
        self::assertSame(['SMS', 's.dubois@email.fr', '24,50 €'], [$this->notifications->messages[0]->channel, $this->notifications->messages[0]->fallbackAddress, $this->notifications->messages[0]->parameters['balanceDue']]);
        self::assertSame([], $this->renderer->rendered);
    }

    public function testAReminderOfACounterSale(): void
    {
        $this->remind([self::I_UNSTORED]);
        $this->worker()(new SendUnpaidReminder($this->jobs->reminders[0]));

        self::assertSame('de vos achats du 16/10/2026', $this->notifications->messages[0]->parameters['careLabel']);
    }

    public function testNothingIsSentWhenThingsChangedInTheMeantimeAndTheDispatchSaysWhy(): void
    {
        $this->worker()(new SendUnpaidReminder('01960000-0000-7000-8000-00000000e599'));

        $paid = $this->log(self::I_PAID, ['EMAIL']);
        $gone = $this->log(self::I_NO_PAYER, ['EMAIL']);
        $arch = $this->log(self::I_ARCHIVED, ['EMAIL']);
        // Reminded by SMS, but the payer has no mobile any more.
        $mobile = $this->log(self::I_MAIL, ['SMS']);

        foreach ([$paid, $gone, $arch, $mobile] as $id) {
            $this->worker()(new SendUnpaidReminder($id));
        }

        self::assertSame([], $this->notifications->messages);
        self::assertSame(
            [InvoiceDispatchOutcome::SKIP_NOTHING_DUE, InvoiceDispatchOutcome::SKIP_NO_CONTACT, InvoiceDispatchOutcome::SKIP_NO_CONTACT, InvoiceDispatchOutcome::SKIP_NO_CONTACT],
            array_map(fn (string $id): ?string => $this->dispatches->dispatches[$id]->skippedReason(), [$paid, $gone, $arch, $mobile]),
        );

        // A replay of a skipped dispatch does nothing more.
        $this->contacts->contacts[self::MAIL] = FakeClientContacts::contact(clientId: self::MAIL);
        $this->worker()(new SendUnpaidReminder($mobile));
        self::assertSame([], $this->notifications->messages);
    }

    public function testAWithdrawnConsentIsHonouredByTheWorker(): void
    {
        $this->remind([self::I_BOTH]);
        // The payer withdrew the SMS from their « Facturation » consent after « Relancer ».
        $this->contacts->contacts[self::BOTH] = FakeClientContacts::contact(clientId: self::BOTH, billing: ['EMAIL']);
        $this->worker()(new SendUnpaidReminder($this->jobs->reminders[0]));

        self::assertSame(['EMAIL'], array_map(static fn ($m): string => $m->channel, $this->notifications->messages));

        // Withdrawn from SMS only, reminded by SMS only: nothing left to send.
        $this->remind([self::I_SMS]);
        $this->contacts->contacts[self::SMS_ONLY] = FakeClientContacts::contact(clientId: self::SMS_ONLY, billing: ['EMAIL']);
        $this->worker()(new SendUnpaidReminder($this->jobs->reminders[1]));

        self::assertCount(1, $this->notifications->messages);
        self::assertSame(InvoiceDispatchOutcome::SKIP_NO_CONTACT, $this->dispatches->dispatches[$this->jobs->reminders[1]]->skippedReason());
    }

    public function testASkippedReminderNeitherCountsNorHoldsBackTheNextOne(): void
    {
        $this->remind([self::I_SMS]);
        $this->invoices->invoices[self::I_SMS] = Invoices::issued(id: self::I_SMS, client: self::SMS_ONLY, status: 'PAID', due: 0);
        $this->worker()(new SendUnpaidReminder($this->jobs->reminders[0]));
        $this->invoices->invoices[self::I_SMS] = Invoices::issued(id: self::I_SMS, client: self::SMS_ONLY, status: 'PARTIALLY_PAID', due: 2450);

        self::assertSame(0, $this->dispatches->countReminders(ClinicId::fromString(Invoices::CLINIC), InvoiceId::fromString(self::I_SMS)));
        self::assertNull($this->dispatches->lastReminderAt(ClinicId::fromString(Invoices::CLINIC), InvoiceId::fromString(self::I_SMS)));
        self::assertSame([self::I_SMS], $this->remind([self::I_SMS])->queued);
    }

    public function testABulkReminderTakesTheOldestInvoicesUpToTheLimit(): void
    {
        $rows = [];

        for ($i = 0; $i <= RemindUnpaidInvoices::MAX_INVOICES; ++$i) {
            $rows[] = Invoices::issued(id: \sprintf('01960000-0000-7000-8000-0000000f%04d', $i), client: self::MAIL);
        }

        $this->invoices = new FakeInvoiceReads(...$rows);
        $preview        = $this->prepare(null);

        self::assertCount(RemindUnpaidInvoices::MAX_INVOICES, $preview->invoiceIds);
        self::assertSame(1, $preview->remainingCount);
        self::assertSame('01960000-0000-7000-8000-0000000f0000', $preview->invoiceIds[0]);
    }

    public function testOneRequestRemindsAtMostTheLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RemindUnpaidInvoices(Invoices::CLINIC, array_fill(0, RemindUnpaidInvoices::MAX_INVOICES + 1, self::I_BOTH));
    }

    /**
     * @param list<string>|null $ids
     */
    private function prepare(?array $ids): \App\Context\Billing\Application\Query\PrepareUnpaidReminder\UnpaidReminderPreview
    {
        return new PrepareUnpaidReminderHandler($this->selection(), $this->segments)(new PrepareUnpaidReminder(Invoices::CLINIC, $ids));
    }

    /**
     * @param list<string> $ids
     */
    private function remind(array $ids): InvoiceDispatchOutcome
    {
        return new RemindUnpaidInvoicesHandler($this->selection(), $this->dispatches, $this->jobs, $this->uuids(), $this->clock())(new RemindUnpaidInvoices(Invoices::CLINIC, $ids, 'actor'));
    }

    private function selection(): UnpaidReminderSelection
    {
        return new UnpaidReminderSelection($this->invoices, $this->dispatches, $this->contacts, $this->clock());
    }

    private function worker(): SendUnpaidReminderHandler
    {
        return new SendUnpaidReminderHandler(
            $this->dispatches,
            $this->invoices,
            $this->contacts,
            new FakeVisits(),
            new SentInvoiceFiles($this->sentFiles, $this->renderer, new InMemoryFiles(), $this->clock()),
            new InvoiceMessages(new FixedClinicTimeZone()),
            $this->notifications,
        );
    }

    private function reminded(string $invoiceId, string $at): void
    {
        $this->log($invoiceId, ['EMAIL'], $at);
    }

    /**
     * @param list<string> $channels
     */
    private function log(string $invoiceId, array $channels, string $at = '2026-10-20 10:00:00'): string
    {
        $id = $this->uuids()->generate();
        $this->dispatches->save(InvoiceDispatch::log(
            InvoiceDispatchId::fromString($id),
            ClinicId::fromString(Invoices::CLINIC),
            InvoiceId::fromString($invoiceId),
            DispatchKind::REMINDER,
            $channels,
            null,
            new \DateTimeImmutable($at, new \DateTimeZone('UTC')),
        ));

        return $id;
    }

    private function uuids(): UuidGeneratorInterface
    {
        return new class($this->sequence) implements UuidGeneratorInterface {
            public function __construct(private int &$sequence)
            {
            }

            public function generate(): string
            {
                return \sprintf('01960000-0000-7000-8000-0000000ea%03d', ++$this->sequence);
            }
        };
    }

    private function clock(): FrozenClock
    {
        return new FrozenClock(new \DateTimeImmutable('2026-10-26 10:00:00', new \DateTimeZone('UTC')));
    }
}
