<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

/**
 * Stores a file sent with one message, under a key derived from the
 * message's idempotency key: a retry overwrites the same file.
 */
interface AttachmentStoreInterface
{
    public function store(string $idempotencyKey, string $filename, string $mime, string $content): StoredAttachment;
}
