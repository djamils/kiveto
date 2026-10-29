<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Adapter\Notification;

use App\Context\Scheduling\Application\Port\AttachmentStoreInterface;
use App\Context\Scheduling\Application\Port\StoredAttachment;
use App\Shared\Domain\Storage\FileStorageInterface;

/**
 * Send-only files under `notification/attachments/<sha256 of the
 * idempotency key>/<filename>` (deterministic: a retry overwrites the same
 * file), where Notification purges them with the message content.
 */
final readonly class FileStorageAttachmentStore implements AttachmentStoreInterface
{
    public function __construct(private FileStorageInterface $files)
    {
    }

    public function store(string $idempotencyKey, string $filename, string $mime, string $content): StoredAttachment
    {
        $key = 'notification/attachments/' . hash('sha256', $idempotencyKey) . '/' . $filename;
        $this->files->storeAt($key, $content);

        return new StoredAttachment($key, $filename, $mime);
    }
}
