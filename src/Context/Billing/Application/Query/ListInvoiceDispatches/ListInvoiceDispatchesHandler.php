<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\ListInvoiceDispatches;

use App\Context\Billing\Application\Port\DeliveryStatusReaderInterface;
use App\Context\Billing\Domain\Dispatch\DispatchKind;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatch;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatchRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The dispatch log of an invoice, its messages' statuses read from
 * Notification in one query (AC 25c: a bounced e-mail reads « Non délivré »,
 * not a plain « Envoyée »). A reminder the worker skipped is listed with its
 * reason but neither counts nor holds back « Relancer ».
 */
#[AsMessageHandler]
final readonly class ListInvoiceDispatchesHandler
{
    private const string ZONE = 'Europe/Paris';

    public function __construct(
        private InvoiceDispatchRepositoryInterface $dispatches,
        private DeliveryStatusReaderInterface $statuses,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ListInvoiceDispatches $query): InvoiceDispatchHistory
    {
        $dispatches = $this->dispatches->listForInvoice(ClinicId::fromString($query->clinicId), InvoiceId::fromString($query->invoiceId));
        $ids        = array_merge(...array_map(static fn (InvoiceDispatch $d): array => $d->notificationIds(), $dispatches));
        $statuses   = [] !== $ids ? $this->statuses->statuses($ids) : [];
        $today      = $this->clock->now()->setTimezone(new \DateTimeZone(self::ZONE))->setTime(0, 0);
        $reminders  = array_values(array_filter($dispatches, static fn (InvoiceDispatch $d): bool => DispatchKind::REMINDER === $d->kind() && !$d->wasSkipped()));

        return new InvoiceDispatchHistory(
            dispatches: array_map(static fn (InvoiceDispatch $d): InvoiceDispatchView => new InvoiceDispatchView(
                kind: $d->kind()->value,
                at: $d->createdAt(),
                authorUserId: $d->authorUserId(),
                channels: $d->channels(),
                deliveries: array_values(array_filter(array_map(static fn (string $id) => $statuses[$id] ?? null, $d->notificationIds()))),
                skippedReason: $d->skippedReason(),
            ), $dispatches),
            reminderCount: \count($reminders),
            remindedToday: [] !== array_filter($reminders, static fn (InvoiceDispatch $d): bool => $d->createdAt() >= $today),
        );
    }
}
