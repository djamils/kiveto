<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Port;

/**
 * A file handed to the e-mail transport: an attachment, or the inline clinic
 * logo (its file name is then the content id the HTML references).
 */
final readonly class OutgoingAttachment
{
    public function __construct(
        public string $filename,
        public string $content,
        public string $mime,
    ) {
    }
}
