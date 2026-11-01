<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

final readonly class PortalClientContact
{
    /**
     * @param string|null $mobile               the SMS-capable phone on file (E.164), primary first
     * @param string|null $transactionalChannel `sms` or `email`: the single channel of a transactional message, null when unreachable
     */
    public function __construct(
        public string $clientId,
        public string $displayName,
        public string $firstName,
        public ?string $email,
        public ?string $mobile,
        public bool $archived,
        public ?string $transactionalChannel = null,
    ) {
    }
}
