<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\EnsureSentInvoiceFile;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The PDF of an issued invoice as it is sent to the client: rendered and
 * stored at its first send, then the same file for every later send and
 * reminder. Public: other contexts attach it through the command bus
 * (e.g. Consultation's documents e-mail). Returns a SentDocumentFile; the
 * file stays Billing's (attach it with `owner = REQUESTER`).
 *
 * @throws \DomainException when the invoice is unknown to this clinic
 */
final readonly class EnsureSentInvoiceFile implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $invoiceId,
    ) {
    }
}
