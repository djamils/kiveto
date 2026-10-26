<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\SendInvoiceToClient;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Envoyer » on one invoice or a selection: each issued invoice whose payer
 * has an e-mail is logged as sent and its e-mail queued; the others are
 * skipped with a reason. Nothing is rendered here (AC 25): the handler
 * returns an InvoiceDispatchOutcome at once.
 */
final readonly class SendInvoiceToClient implements CommandInterface
{
    /**
     * @param list<string> $invoiceIds
     */
    public function __construct(
        public string $clinicId,
        public array $invoiceIds,
        public ?string $actorUserId = null,
    ) {
    }
}
