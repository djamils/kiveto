<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Port;

/**
 * An SMS ready for the transport: alphanumeric sender, E.164 recipient, text
 * and the idempotency key as provider tag.
 */
final readonly class OutgoingSms
{
    public function __construct(
        public string $from,
        public string $to,
        public string $text,
        public string $tag,
    ) {
    }
}
