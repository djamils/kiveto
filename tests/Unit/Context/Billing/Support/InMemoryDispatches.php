<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Support;

use App\Context\Billing\Domain\Dispatch\DispatchKind;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatch;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatchId;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatchRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;

final class InMemoryDispatches implements InvoiceDispatchRepositoryInterface
{
    /** @var array<string, InvoiceDispatch> */
    public array $dispatches = [];

    /** @var list<list<string>> the invoice ids of each lockInvoices() call */
    public array $locked = [];

    public function save(InvoiceDispatch $dispatch): void
    {
        $this->dispatches[$dispatch->id()->toString()] = $dispatch;
    }

    public function find(InvoiceDispatchId $id): ?InvoiceDispatch
    {
        return $this->dispatches[$id->toString()] ?? null;
    }

    public function listForInvoice(ClinicId $clinicId, InvoiceId $invoiceId): array
    {
        return array_values(array_filter(
            $this->dispatches,
            static fn (InvoiceDispatch $d): bool => $d->clinicId()->equals($clinicId) && $d->invoiceId()->equals($invoiceId),
        ));
    }

    public function countReminders(ClinicId $clinicId, InvoiceId $invoiceId): int
    {
        return \count($this->reminders($clinicId, $invoiceId));
    }

    public function lastReminderAt(ClinicId $clinicId, InvoiceId $invoiceId): ?\DateTimeImmutable
    {
        $last = null;

        foreach ($this->reminders($clinicId, $invoiceId) as $dispatch) {
            $last = null === $last || $dispatch->createdAt() > $last ? $dispatch->createdAt() : $last;
        }

        return $last;
    }

    public function lockInvoices(ClinicId $clinicId, array $invoiceIds): void
    {
        $this->locked[] = array_map(static fn (InvoiceId $id): string => $id->toString(), $invoiceIds);
    }

    public function remindedSince(ClinicId $clinicId, \DateTimeImmutable $since): array
    {
        $ids = [];

        foreach ($this->dispatches as $dispatch) {
            if ($dispatch->clinicId()->equals($clinicId) && DispatchKind::REMINDER === $dispatch->kind() && !$dispatch->wasSkipped() && $dispatch->createdAt() >= $since) {
                $ids[] = $dispatch->invoiceId()->toString();
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<InvoiceDispatch>
     */
    private function reminders(ClinicId $clinicId, InvoiceId $invoiceId): array
    {
        return array_values(array_filter(
            $this->listForInvoice($clinicId, $invoiceId),
            static fn (InvoiceDispatch $d): bool => DispatchKind::REMINDER === $d->kind() && !$d->wasSkipped(),
        ));
    }
}
