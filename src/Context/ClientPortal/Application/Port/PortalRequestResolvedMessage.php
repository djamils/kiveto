<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/**
 * The clinic's answer to a request, for its owner: a fixed French sentence
 * (« Votre demande de … pour … a été traitée. » / « … n'a pas pu être
 * acceptée. ») and the staff's message, '' when none.
 */
final readonly class PortalRequestResolvedMessage
{
    /**
     * @param string  $channel       sms|email
     * @param ?string $fallbackEmail an SMS's e-mail fallback when the clinic's SMS cap is reached
     */
    public function __construct(
        public string $requestId,
        public string $clinicId,
        public string $clientId,
        public string $channel,
        public string $recipientAddress,
        public string $recipientName,
        public string $petName,
        public string $sentence,
        public string $staffMessage,
        public ?string $fallbackEmail,
    ) {
    }
}
