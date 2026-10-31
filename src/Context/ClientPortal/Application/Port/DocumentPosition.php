<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** The sort keys of the last document of a page (`issued_at`, `id`). */
final readonly class DocumentPosition
{
    public function __construct(
        public \DateTimeImmutable $issuedAt,
        public string $id,
    ) {
    }
}
