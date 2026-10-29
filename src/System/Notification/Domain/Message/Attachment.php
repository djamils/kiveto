<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Message;

/**
 * A file attached to an e-mail, stored under a deterministic key by the
 * requester; binaries never travel in messages.
 */
final readonly class Attachment
{
    private function __construct(
        public string $storageKey,
        public string $filename,
        public string $mime,
        public AttachmentOwner $owner,
    ) {
    }

    public static function of(string $storageKey, string $filename, string $mime, AttachmentOwner $owner): self
    {
        if ('' === mb_trim($storageKey) || '' === mb_trim($filename) || '' === mb_trim($mime)) {
            throw new \InvalidArgumentException('An attachment needs a storage key, a file name and a content type.');
        }

        return new self($storageKey, $filename, $mime, $owner);
    }
}
