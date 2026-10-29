<?php

declare(strict_types=1);

namespace App\Shared\Application\Document;

/**
 * The band repeated at the bottom of every page: the clinic's identity, what
 * the document is ("Facture · FA-2026-0814") and who it is about.
 */
final readonly class DocumentFooter
{
    public function __construct(
        public ?DocumentClinic $clinic,
        public string $reference,
        public ?string $subject = null,
    ) {
    }
}
