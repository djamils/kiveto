<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** An active consent of a client file: its channels and the date it was recorded. */
final readonly class ClientConsentGrant
{
    /**
     * @param list<string> $channels
     */
    public function __construct(
        public string $kind,
        public array $channels,
        public \DateTimeImmutable $grantedAt,
    ) {
    }
}
