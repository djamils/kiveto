<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

/**
 * A document PDF stored under a storage key, attached by reference.
 */
final readonly class StoredDocument
{
    public const string MIME = 'application/pdf';

    /**
     * @param string $filename the name the client sees: « ORD-2026-000391.pdf »
     */
    public function __construct(
        public string $storageKey,
        public string $filename,
    ) {
    }
}
