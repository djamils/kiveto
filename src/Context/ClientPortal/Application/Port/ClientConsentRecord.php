<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/**
 * The consents of one client file: its preferred channel, whether it is
 * archived, and its active consents keyed by kind.
 */
final readonly class ClientConsentRecord
{
    /**
     * @param array<string, ClientConsentGrant> $consents
     */
    public function __construct(
        public string $clientId,
        public ?string $preferredChannel,
        public bool $archived,
        public array $consents,
    ) {
    }
}
