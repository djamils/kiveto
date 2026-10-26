<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\SendInvoiceToClient;

use App\Context\Billing\Application\Port\ClientContact;
use App\Context\Billing\Application\Port\ClientContactInterface;
use App\Context\Billing\Application\Port\DispatchJobsInterface;
use App\Context\Billing\Application\Port\InvoiceReadRepositoryInterface;
use App\Context\Billing\Application\Query\GetInvoiceDetails\InvoiceDetails;
use App\Context\Billing\Domain\Dispatch\DispatchKind;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatch;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatchId;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatchRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceStatus;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Validates, logs the SEND dispatch and queues SendInvoiceEmail, per
 * invoice, in one transaction: the e-mail is queued with its log or not at
 * all. The payers are read in one query.
 */
#[AsMessageHandler]
final readonly class SendInvoiceToClientHandler
{
    public function __construct(
        private InvoiceReadRepositoryInterface $invoices,
        private ClientContactInterface $contacts,
        private InvoiceDispatchRepositoryInterface $dispatches,
        private DispatchJobsInterface $jobs,
        private UuidGeneratorInterface $uuids,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SendInvoiceToClient $command): InvoiceDispatchOutcome
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $skipped  = [];
        $sendable = [];

        foreach (array_values(array_unique($command->invoiceIds)) as $invoiceId) {
            $invoice = $this->details($clinicId, $invoiceId);
            $reason  = null === $invoice ? InvoiceDispatchOutcome::SKIP_UNKNOWN : self::unsendable($invoice);

            if (null !== $reason) {
                $skipped[$invoiceId] = $reason;
            } else {
                \assert(null !== $invoice);
                $sendable[$invoiceId] = $invoice;
            }
        }

        $payers   = array_values(array_unique(array_filter(array_map(static fn (InvoiceDetails $i): ?string => $i->payerClientId, $sendable))));
        $contacts = [] !== $payers ? $this->contacts->contactsOf($clinicId->toString(), $payers) : [];
        $queued   = [];
        $now      = $this->clock->now();

        foreach ($sendable as $invoiceId => $invoice) {
            $contact = null !== $invoice->payerClientId ? ($contacts[$invoice->payerClientId] ?? null) : null;

            if (null === $contact || !$contact->canReceiveInvoice()) {
                $skipped[$invoiceId] = InvoiceDispatchOutcome::SKIP_NO_EMAIL;

                continue;
            }

            $dispatch = InvoiceDispatch::log(
                InvoiceDispatchId::fromString($this->uuids->generate()),
                $clinicId,
                InvoiceId::fromString($invoiceId),
                DispatchKind::SEND,
                [ClientContact::EMAIL],
                $command->actorUserId,
                $now,
            );
            $this->dispatches->save($dispatch);
            $this->jobs->sendInvoiceEmail($dispatch->id()->toString());
            $queued[] = $invoiceId;
        }

        return new InvoiceDispatchOutcome($queued, $skipped);
    }

    /** Statuses an invoice is sent in: issued, whether paid or not. */
    public static function isSendable(string $status): bool
    {
        return \in_array($status, [InvoiceStatus::ISSUED->value, InvoiceStatus::PARTIALLY_PAID->value, InvoiceStatus::PAID->value], true);
    }

    private function details(ClinicId $clinicId, string $invoiceId): ?InvoiceDetails
    {
        try {
            return $this->invoices->details($clinicId, InvoiceId::fromString($invoiceId));
        } catch (\LogicException) { // unknown to the clinic (DomainException) or malformed (InvalidArgumentException)
            return null;
        }
    }

    private static function unsendable(InvoiceDetails $invoice): ?string
    {
        return match (true) {
            InvoiceStatus::DRAFT->value === $invoice->status => InvoiceDispatchOutcome::SKIP_DRAFT,
            !self::isSendable($invoice->status)              => InvoiceDispatchOutcome::SKIP_NOT_ISSUED,
            default                                          => null,
        };
    }
}
