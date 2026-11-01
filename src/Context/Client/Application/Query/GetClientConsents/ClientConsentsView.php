<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\GetClientConsents;

/**
 * The consents of one client record: its active consents by kind, the
 * preferred channel and whether the record is archived (an archived client
 * keeps its consents but receives no optional message).
 */
final readonly class ClientConsentsView
{
    /**
     * @param array<string, ClientConsentView> $consents active consents only, keyed by ConsentKind value
     */
    public function __construct(
        public string $clientId,
        public ?string $preferredChannel,
        public bool $archived,
        public array $consents,
    ) {
    }

    public function consentFor(string $kind): ?ClientConsentView
    {
        return $this->consents[$kind] ?? null;
    }
}
