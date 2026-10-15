<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Query\PeekNextDocumentNumbers;

final readonly class NextDocumentNumbers
{
    public function __construct(
        /** « FAC-2026-000815 » */
        public string $invoice,
        /** « AV-2026-000012 » */
        public string $creditNote,
    ) {
    }
}
