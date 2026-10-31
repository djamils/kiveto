<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** A source document (invoice, prescription) the go-live import publishes. */
final readonly class PublishableSource
{
    public function __construct(
        public string $id,
        public string $clinicId,
    ) {
    }
}
