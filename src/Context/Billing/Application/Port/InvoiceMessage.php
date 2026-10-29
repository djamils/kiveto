<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

use App\Context\Billing\Application\Command\EnsureSentInvoiceFile\SentDocumentFile;

/**
 * One message about an invoice: its purpose (Notification's INVOICE_SENT or
 * INVOICE_REMINDER), one channel and the parameters its template prints.
 */
final readonly class InvoiceMessage
{
    public const string SENT     = 'INVOICE_SENT';
    public const string REMINDER = 'INVOICE_REMINDER';

    /**
     * @param string                                  $channel            ClientContact::EMAIL or ClientContact::SMS
     * @param array<string, string|list<string>>      $parameters         exactly what the template of the purpose and channel declares
     * @param SentDocumentFile|null                   $attachment         e-mail only: the invoice PDF, Billing's file
     * @param array<string, string|list<string>>|null $fallbackParameters the e-mail sent instead of a reminder SMS held back by
     *                                                                    the clinic's SMS cap, to $fallbackAddress (null: none)
     */
    public function __construct(
        public string $purpose,
        public string $channel,
        public string $recipientAddress,
        public string $recipientName,
        public string $clinicId,
        public string $clientId,
        public string $invoiceId,
        public string $idempotencyKey,
        public array $parameters,
        public ?SentDocumentFile $attachment = null,
        public ?string $fallbackAddress = null,
        public ?array $fallbackParameters = null,
    ) {
    }
}
