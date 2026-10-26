<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Dispatch;

use App\Context\Billing\Application\Command\SendInvoiceToClient\InvoiceDispatchOutcome;
use App\Context\Billing\Application\Port\ClientContactInterface;
use App\Context\Billing\Application\Port\InvoiceReadRepositoryInterface;
use App\Context\Billing\Application\Port\OutstandingInvoiceRow;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatchRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Which invoices an unpaid reminder may go to (AC 26): issued or partly paid
 * with something left to pay (credit notes never are), not reminded yet
 * today — the day of Europe/Paris —, and a payer reachable on the
 * « Facturation » channels, else on their transactional channel.
 */
final readonly class UnpaidReminderSelection
{
    private const string ZONE = 'Europe/Paris';

    public function __construct(
        private InvoiceReadRepositoryInterface $invoices,
        private InvoiceDispatchRepositoryInterface $dispatches,
        private ClientContactInterface $contacts,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param list<string>|null $invoiceIds null: every unpaid invoice of the clinic
     *
     * @return array{list<ReminderCandidate>, array<string, string>} the candidates, and the skipped ids with their reason
     */
    public function select(string $clinicId, ?array $invoiceIds): array
    {
        $clinic  = ClinicId::fromString($clinicId);
        $skipped = [];
        $only    = null;

        if (null !== $invoiceIds) {
            $only = [];

            foreach (array_values(array_unique($invoiceIds)) as $id) {
                if (Uuid::isValid($id)) {
                    $only[$id] = InvoiceId::fromString($id);
                } else {
                    $skipped[$id] = InvoiceDispatchOutcome::SKIP_NOTHING_DUE;
                }
            }
        }

        $rows = array_values(array_filter(
            $this->invoices->outstandingInvoices($clinic, null !== $only ? array_values($only) : null),
            static fn (OutstandingInvoiceRow $row): bool => $row->balanceDueMinorUnits > 0,
        ));

        foreach (array_keys($only ?? []) as $id) {
            if ([] === array_filter($rows, static fn (OutstandingInvoiceRow $row): bool => $row->id === $id)) {
                $skipped[$id] = InvoiceDispatchOutcome::SKIP_NOTHING_DUE;
            }
        }

        $today    = array_flip($this->dispatches->remindedSince($clinic, $this->startOfToday()));
        $payers   = array_values(array_unique(array_filter(array_map(static fn (OutstandingInvoiceRow $row): ?string => $row->payerClientId, $rows))));
        $contacts = [] !== $payers ? $this->contacts->contactsOf($clinicId, $payers) : [];
        $eligible = [];

        foreach ($rows as $row) {
            $contact  = null !== $row->payerClientId ? ($contacts[$row->payerClientId] ?? null) : null;
            $channels = null !== $contact ? $contact->reminderChannels() : [];

            if (isset($today[$row->id])) {
                $skipped[$row->id] = InvoiceDispatchOutcome::SKIP_REMINDED_TODAY;
            } elseif (null === $contact || [] === $channels) {
                $skipped[$row->id] = InvoiceDispatchOutcome::SKIP_NO_CONTACT;
            } else {
                $eligible[] = new ReminderCandidate($row, $contact, $channels);
            }
        }

        return [$eligible, $skipped];
    }

    private function startOfToday(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone(self::ZONE))->setTime(0, 0);
    }
}
