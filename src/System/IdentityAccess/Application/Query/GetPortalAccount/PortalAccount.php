<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\GetPortalAccount;

/** Unmasked: masking the phone is the API's business. */
final readonly class PortalAccount
{
    public function __construct(
        public string $userId,
        public ?string $email,
        public string $phone,
        /** The owner proved the e-mail (a code sent to it). */
        public bool $emailVerified = false,
    ) {
    }
}
