<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Port;

/**
 * An e-mail ready for the transport. The tag is the message's idempotency
 * key, sent to the provider so that a duplicate send can be traced.
 */
final readonly class OutgoingEmail
{
    /**
     * @param list<OutgoingAttachment> $attachments
     */
    public function __construct(
        public string $fromName,
        public string $fromAddress,
        public ?string $replyTo,
        public string $toAddress,
        public string $toName,
        public string $subject,
        public string $html,
        public string $text,
        public string $tag,
        public array $attachments = [],
        public ?OutgoingAttachment $inlineLogo = null,
    ) {
    }
}
