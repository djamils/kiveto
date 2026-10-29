<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Billing\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Billing\Application\Command\EnsureSentInvoiceFile\SentDocumentFile;
use App\Context\Billing\Application\Port\SentDocumentFileStoreInterface;
use App\Context\Billing\Domain\Dispatch\DispatchKind;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatch;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatchId;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatchRepositoryInterface;
use App\Context\Billing\Domain\Dispatch\SentDocumentKind;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity\InvoiceDispatchEntity;
use App\Context\Billing\Infrastructure\Persistence\Doctrine\Entity\SentDocumentFileEntity;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class InvoiceDispatchPersistenceTest extends KernelTestCase
{
    private const string CLINIC  = '01960000-0000-7000-8000-00000000d201';
    private const string OTHER   = '01960000-0000-7000-8000-00000000d202';
    private const string INVOICE = '01960000-0000-7000-8000-00000000d203';
    private const string AUTHOR  = '01960000-0000-7000-8000-00000000d204';

    public function testTheLogKeepsSendsAndRemindersOfAnInvoice(): void
    {
        $repository = $this->dispatches();
        $send       = $this->dispatch('01960000-0000-7000-8000-00000000d211', DispatchKind::SEND, ['EMAIL'], '2026-10-20 09:00:00', self::AUTHOR);
        $first      = $this->dispatch('01960000-0000-7000-8000-00000000d212', DispatchKind::REMINDER, ['EMAIL', 'SMS'], '2026-10-24 22:30:00');
        $second     = $this->dispatch('01960000-0000-7000-8000-00000000d213', DispatchKind::REMINDER, ['SMS'], '2026-10-26 08:00:00');

        foreach ([$second, $send, $first] as $dispatch) {
            $repository->save($dispatch);
        }

        $send->recordNotifications(['01960000-0000-7000-8000-00000000d299']);
        $repository->save($send);

        $found = $repository->find($send->id());
        self::assertNotNull($found);
        self::assertSame(['EMAIL'], $found->channels());
        self::assertSame(['01960000-0000-7000-8000-00000000d299'], $found->notificationIds());
        self::assertSame(self::AUTHOR, $found->authorUserId());
        self::assertSame('2026-10-20 09:00:00', $found->createdAt()->format('Y-m-d H:i:s'));
        self::assertNull($repository->find(InvoiceDispatchId::fromString(self::OTHER)));

        $list = $repository->listForInvoice(ClinicId::fromString(self::CLINIC), InvoiceId::fromString(self::INVOICE));
        self::assertSame([$send->id()->toString(), $first->id()->toString(), $second->id()->toString()], array_map(static fn (InvoiceDispatch $d): string => $d->id()->toString(), $list));
        self::assertSame([], $repository->listForInvoice(ClinicId::fromString(self::OTHER), InvoiceId::fromString(self::INVOICE)));

        self::assertSame(2, $repository->countReminders(ClinicId::fromString(self::CLINIC), InvoiceId::fromString(self::INVOICE)));
        self::assertSame('2026-10-26 08:00:00', $repository->lastReminderAt(ClinicId::fromString(self::CLINIC), InvoiceId::fromString(self::INVOICE))?->format('Y-m-d H:i:s'));
        self::assertNull($repository->lastReminderAt(ClinicId::fromString(self::OTHER), InvoiceId::fromString(self::INVOICE)));

        self::assertSame([self::INVOICE], $repository->remindedSince(ClinicId::fromString(self::CLINIC), new \DateTimeImmutable('2026-10-25 23:00:00')));
        self::assertSame([], $repository->remindedSince(ClinicId::fromString(self::CLINIC), new \DateTimeImmutable('2026-10-26 09:00:00')));

        // Nothing to lock: no query (an empty IN () is no SQL).
        $repository->lockInvoices(ClinicId::fromString(self::CLINIC), []);
    }

    public function testASkippedReminderIsKeptButNeitherCountsNorHoldsBack(): void
    {
        $repository = $this->dispatches();
        $skipped    = $this->dispatch('01960000-0000-7000-8000-00000000d221', DispatchKind::REMINDER, ['SMS'], '2026-10-26 08:00:00');
        $repository->save($skipped);
        $skipped->skip('rien à relancer');
        $repository->save($skipped);

        self::assertSame('rien à relancer', $repository->find($skipped->id())?->skippedReason());
        self::assertSame(0, $repository->countReminders(ClinicId::fromString(self::CLINIC), InvoiceId::fromString(self::INVOICE)));
        self::assertNull($repository->lastReminderAt(ClinicId::fromString(self::CLINIC), InvoiceId::fromString(self::INVOICE)));
        self::assertSame([], $repository->remindedSince(ClinicId::fromString(self::CLINIC), new \DateTimeImmutable('2026-10-25 23:00:00')));
    }

    public function testARedeliveredSendNeverOverwritesTheRecordedOutcome(): void
    {
        $repository = $this->dispatches();
        $repository->save($this->dispatch('01960000-0000-7000-8000-00000000d231', DispatchKind::SEND, ['EMAIL'], '2026-10-26 08:00:00'));

        // Two workers loaded the dispatch before either recorded anything.
        $first  = $repository->find(InvoiceDispatchId::fromString('01960000-0000-7000-8000-00000000d231'));
        $second = $repository->find(InvoiceDispatchId::fromString('01960000-0000-7000-8000-00000000d231'));
        self::assertNotNull($first);
        self::assertNotNull($second);

        $first->recordNotifications(['01960000-0000-7000-8000-00000000d298']);
        $repository->save($first);
        $second->recordNotifications(['01960000-0000-7000-8000-00000000d297']);
        $repository->save($second);

        $stale = $repository->find($first->id());
        self::assertSame(['01960000-0000-7000-8000-00000000d298'], $stale?->notificationIds());

        $late = $repository->find(InvoiceDispatchId::fromString('01960000-0000-7000-8000-00000000d231'));
        self::assertNotNull($late);
        $late = InvoiceDispatch::reconstitute($late->id(), $late->clinicId(), $late->invoiceId(), $late->kind(), $late->channels(), [], null, $late->createdAt());
        $late->skip('sans adresse e-mail');
        $repository->save($late);

        $found = $repository->find($first->id());
        self::assertSame([['01960000-0000-7000-8000-00000000d298'], null], [$found?->notificationIds(), $found?->skippedReason()]);
    }

    public function testASentFileIsRecordedOnceAndPerClinic(): void
    {
        $store = self::getContainer()->get('test.' . SentDocumentFileStoreInterface::class);
        \assert($store instanceof SentDocumentFileStoreInterface);
        $at = new \DateTimeImmutable('2026-10-26 10:00:00');

        self::assertNull($store->find(self::CLINIC, self::INVOICE));

        $first = $store->record(self::CLINIC, SentDocumentKind::INVOICE, self::INVOICE, new SentDocumentFile('billing/sent/invoice/a.pdf', 'FAC-1.pdf'), $at);
        $again = $store->record(self::CLINIC, SentDocumentKind::INVOICE, self::INVOICE, new SentDocumentFile('billing/sent/invoice/b.pdf', 'FAC-2.pdf'), $at);

        self::assertSame(['billing/sent/invoice/a.pdf', 'FAC-1.pdf'], [$first->storageKey, $first->filename]);
        self::assertSame((array) $first, (array) $again);
        self::assertSame((array) $first, (array) $store->find(self::CLINIC, self::INVOICE));
        self::assertNull($store->find(self::OTHER, self::INVOICE));

        $this->expectException(\LogicException::class);
        $store->record(self::OTHER, SentDocumentKind::INVOICE, self::INVOICE, new SentDocumentFile('billing/sent/invoice/c.pdf', 'FAC-3.pdf'), $at);
    }

    public function testTheEntitiesOnlyMapTheTables(): void
    {
        $id = Uuid::fromString(self::INVOICE);
        $at = new \DateTimeImmutable();

        self::assertSame(
            ['id' => $id, 'clinic_id' => $id, 'invoice_id' => $id, 'kind' => 'SEND', 'channels' => ['EMAIL'], 'notification_ids' => [], 'created_at' => $at, 'author_user_id' => null, 'skipped_reason' => null],
            new InvoiceDispatchEntity($id, $id, $id, 'SEND', ['EMAIL'], [], $at)->columns(),
        );
        self::assertSame(
            ['document_id' => $id, 'clinic_id' => $id, 'document_kind' => 'INVOICE', 'storage_key' => 'k', 'filename' => 'f.pdf', 'created_at' => $at],
            new SentDocumentFileEntity($id, $id, 'INVOICE', 'k', 'f.pdf', $at)->columns(),
        );
    }

    /**
     * @param list<string> $channels
     */
    private function dispatch(string $id, DispatchKind $kind, array $channels, string $at, ?string $author = null): InvoiceDispatch
    {
        return InvoiceDispatch::log(
            InvoiceDispatchId::fromString($id),
            ClinicId::fromString(self::CLINIC),
            InvoiceId::fromString(self::INVOICE),
            $kind,
            $channels,
            $author,
            new \DateTimeImmutable($at, new \DateTimeZone('UTC')),
        );
    }

    private function dispatches(): InvoiceDispatchRepositoryInterface
    {
        $repository = self::getContainer()->get('test.' . InvoiceDispatchRepositoryInterface::class);
        \assert($repository instanceof InvoiceDispatchRepositoryInterface);

        return $repository;
    }
}
