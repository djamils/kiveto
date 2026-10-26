<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\EnsureSentInvoiceFile;

/**
 * A document PDF stored by Billing under a deterministic key, kept as long
 * as the document (accounting retention).
 */
final readonly class SentDocumentFile
{
    public const string MIME = 'application/pdf';

    /**
     * @param string $storageKey `billing/sent/invoice/<id>.pdf`
     * @param string $filename   « FAC-2026-000012.pdf »
     */
    public function __construct(
        public string $storageKey,
        public string $filename,
    ) {
    }
}
