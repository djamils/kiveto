<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

/**
 * A file stored for one message only (Notification deletes it with the
 * message content).
 */
final readonly class StoredAttachment
{
    public function __construct(
        public string $storageKey,
        public string $filename,
        public string $mime,
    ) {
    }
}
