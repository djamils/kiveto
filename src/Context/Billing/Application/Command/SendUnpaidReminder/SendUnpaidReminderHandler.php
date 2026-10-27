<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\SendUnpaidReminder;

use App\Context\Billing\Application\Command\SendInvoiceToClient\InvoiceDispatchOutcome;
use App\Context\Billing\Application\Dispatch\InvoiceMessages;
use App\Context\Billing\Application\Port\ClientContact;
use App\Context\Billing\Application\Port\ClientContactInterface;
use App\Context\Billing\Application\Port\InvoiceReadRepositoryInterface;
use App\Context\Billing\Application\Port\NotificationRequesterInterface;
use App\Context\Billing\Application\Port\VisitDirectoryInterface;
use App\Context\Billing\Application\Service\SentInvoiceFiles;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatch;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatchId;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatchRepositoryInterface;
use App\Context\Billing\Domain\ValueObject\InvoiceStatus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Network first, writes last: on an e-mail reminder the stored PDF is
 * ensured (rendered at the invoice's first send only) before Notification is
 * asked for the INVOICE_REMINDER e-mail (with the PDF) and / or SMS
 * (non-critical: the e-mail fallback goes with it only when e-mail is not
 * already a channel); the message ids are recorded on the dispatch. Nothing
 * is sent for an invoice settled in the meantime, nor on a channel whose
 * address or « Facturation » consent disappeared — a dispatch with nothing
 * left to send is recorded as skipped, with the reason; a dispatch already
 * settled is left alone (replay).
 */
#[AsMessageHandler]
final readonly class SendUnpaidReminderHandler
{
    public function __construct(
        private InvoiceDispatchRepositoryInterface $dispatches,
        private InvoiceReadRepositoryInterface $invoices,
        private ClientContactInterface $contacts,
        private VisitDirectoryInterface $visits,
        private SentInvoiceFiles $files,
        private InvoiceMessages $messages,
        private NotificationRequesterInterface $notifications,
    ) {
    }

    public function __invoke(SendUnpaidReminder $command): void
    {
        $dispatch = $this->dispatches->find(InvoiceDispatchId::fromString($command->dispatchId));

        if (null === $dispatch || $dispatch->isSettled()) {
            return;
        }

        $clinicId    = $dispatch->clinicId()->toString();
        $invoice     = $this->invoices->details($dispatch->clinicId(), $dispatch->invoiceId());
        $contact     = null !== $invoice->payerClientId ? $this->contacts->contactOf($clinicId, $invoice->payerClientId) : null;
        $outstanding = InvoiceStatus::from($invoice->status)->isOutstanding() && $invoice->balanceDueMinorUnits > 0;

        if (!$outstanding) {
            $this->skip($dispatch, InvoiceDispatchOutcome::SKIP_NOTHING_DUE);

            return;
        }

        // The consent is read again: a channel withdrawn since « Relancer » is not used.
        $channels = null !== $contact ? $contact->reminderChannels() : [];
        $byEmail  = null !== $contact?->email && $dispatch->sendsOn(ClientContact::EMAIL) && \in_array(ClientContact::EMAIL, $channels, true);
        $bySms    = null !== $contact?->mobile && $dispatch->sendsOn(ClientContact::SMS) && \in_array(ClientContact::SMS, $channels, true);

        if (null === $contact || (!$byEmail && !$bySms)) {
            $this->skip($dispatch, InvoiceDispatchOutcome::SKIP_NO_CONTACT);

            return;
        }

        $file  = $byEmail ? $this->files->ensure($clinicId, $invoice->id) : null;
        $visit = null !== $invoice->admissionId ? $this->visits->visitOf($clinicId, $invoice->admissionId) : null;
        $ids   = [];

        if (null !== $file) {
            $ids[] = $this->notifications->request($this->messages->reminderEmail($dispatch, $invoice, $contact, $visit, $file));
        }

        if ($bySms) {
            $ids[] = $this->notifications->request($this->messages->reminderSms($dispatch, $invoice, $contact, $visit));
        }

        $dispatch->recordNotifications($ids);
        $this->dispatches->save($dispatch);
    }

    private function skip(InvoiceDispatch $dispatch, string $reason): void
    {
        $dispatch->skip($reason);
        $this->dispatches->save($dispatch);
    }
}
