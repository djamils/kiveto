<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Support;

use App\Context\Scheduling\Application\Port\AttachmentStoreInterface;
use App\Context\Scheduling\Application\Port\StoredAttachment;

final class InMemoryAttachments implements AttachmentStoreInterface
{
    /** @var array<string, string> storage key → content */
    public array $files = [];

    public function store(string $idempotencyKey, string $filename, string $mime, string $content): StoredAttachment
    {
        $key               = 'attachments/' . $idempotencyKey . '/' . $filename;
        $this->files[$key] = $content;

        return new StoredAttachment($key, $filename, $mime);
    }
}
