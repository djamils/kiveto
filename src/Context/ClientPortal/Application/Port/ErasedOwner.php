<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/**
 * What still identified the owner account when its erasure started: both
 * null once the account is anonymised (a retry of a completed erasure).
 */
final readonly class ErasedOwner
{
    public function __construct(
        public ?string $phone,
        public ?string $email,
    ) {
    }
}
