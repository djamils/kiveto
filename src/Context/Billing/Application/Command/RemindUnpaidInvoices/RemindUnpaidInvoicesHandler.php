<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\RemindUnpaidInvoices;

use App\Context\Billing\Application\Command\SendInvoiceToClient\InvoiceDispatchOutcome;
use App\Context\Billing\Application\Dispatch\UnpaidReminderSelection;
use App\Context\Billing\Application\Port\DispatchJobsInterface;
use App\Context\Billing\Domain\Dispatch\DispatchKind;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatch;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatchId;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatchRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Locks the invoices first — the transaction's first statement, so that its
 * reads see what a concurrent reminder committed while it waited: a double
 * click or two desks reminding the same invoice end with one reminder (no
 * gap-locking read here: two reminders of different invoices of the clinic
 * would deadlock on their inserts). Then re-checks the
 * eligibility (the confirmation may be stale), logs one REMINDER dispatch per
 * invoice with its channels and queues SendUnpaidReminder, in one
 * transaction.
 */
#[AsMessageHandler]
final readonly class RemindUnpaidInvoicesHandler
{
    public function __construct(
        private UnpaidReminderSelection $selection,
        private InvoiceDispatchRepositoryInterface $dispatches,
        private DispatchJobsInterface $jobs,
        private UuidGeneratorInterface $uuids,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RemindUnpaidInvoices $command): InvoiceDispatchOutcome
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $this->dispatches->lockInvoices($clinicId, array_values(array_map(
            InvoiceId::fromString(...),
            array_unique(array_filter($command->invoiceIds, Uuid::isValid(...))),
        )));

        [$candidates, $skipped] = $this->selection->select($command->clinicId, $command->invoiceIds);
        $now                    = $this->clock->now();
        $queued                 = [];

        foreach ($candidates as $candidate) {
            $dispatch = InvoiceDispatch::log(
                InvoiceDispatchId::fromString($this->uuids->generate()),
                $clinicId,
                InvoiceId::fromString($candidate->invoice->id),
                DispatchKind::REMINDER,
                $candidate->channels,
                $command->actorUserId,
                $now,
            );
            $this->dispatches->save($dispatch);
            $this->jobs->sendUnpaidReminder($dispatch->id()->toString());
            $queued[] = $candidate->invoice->id;
        }

        return new InvoiceDispatchOutcome($queued, $skipped);
    }
}
