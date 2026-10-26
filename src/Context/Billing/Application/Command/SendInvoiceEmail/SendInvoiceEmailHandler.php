<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\SendInvoiceEmail;

use App\Context\Billing\Application\Command\SendInvoiceToClient\SendInvoiceToClientHandler;
use App\Context\Billing\Application\Dispatch\InvoiceMessages;
use App\Context\Billing\Application\Port\ClientContactInterface;
use App\Context\Billing\Application\Port\InvoiceReadRepositoryInterface;
use App\Context\Billing\Application\Port\NotificationRequesterInterface;
use App\Context\Billing\Application\Port\VisitDirectoryInterface;
use App\Context\Billing\Application\Service\SentInvoiceFiles;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatchId;
use App\Context\Billing\Domain\Dispatch\InvoiceDispatchRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Network first, writes last: the invoice PDF is rendered and stored (first
 * send only) before Notification is asked for the INVOICE_SENT e-mail —
 * « Reste dû » with the amount, or « Réglée » — and the message id recorded
 * on the dispatch. A dispatch already sent is left alone (replay); an
 * invoice cancelled or a payer without e-mail in the meantime sends nothing.
 */
#[AsMessageHandler]
final readonly class SendInvoiceEmailHandler
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

    public function __invoke(SendInvoiceEmail $command): void
    {
        $dispatch = $this->dispatches->find(InvoiceDispatchId::fromString($command->dispatchId));

        if (null === $dispatch || $dispatch->wasSent()) {
            return;
        }

        $clinicId = $dispatch->clinicId()->toString();
        $invoice  = $this->invoices->details($dispatch->clinicId(), $dispatch->invoiceId());
        $contact  = null !== $invoice->payerClientId ? $this->contacts->contactOf($clinicId, $invoice->payerClientId) : null;

        if (!SendInvoiceToClientHandler::isSendable($invoice->status) || null === $contact || !$contact->canReceiveInvoice()) {
            return;
        }

        $file  = $this->files->ensure($clinicId, $invoice->id);
        $visit = null !== $invoice->admissionId ? $this->visits->visitOf($clinicId, $invoice->admissionId) : null;

        $dispatch->recordNotifications([$this->notifications->request($this->messages->sent($dispatch, $invoice, $contact, $visit, $file))]);
        $this->dispatches->save($dispatch);
    }
}
